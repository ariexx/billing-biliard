<?php

namespace App\Http\Controllers;

use App\Models\ActiveOrder;
use App\Models\Hour;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderItemController extends Controller
{
    public function update($uuid, Request $request)
    {
        $order = Order::whereUuid($uuid)->firstOrFail();
        $this->authorize('update', $order);

        $data = $request->validate([
            'product' => 'array',
            'product.*' => 'nullable|exists:products,uuid',
            'quantity' => 'array',
            'quantity.*' => 'nullable|integer|min:1',
            'hour' => 'nullable|exists:hours,uuid',
        ]);

        $hour = !empty($data['hour'])
            ? Hour::whereUuid($data['hour'])->firstOrFail()
            : null;

        $session = $order->currentSession();

        if ($hour && $error = $this->validateHourAgainstSession($hour, $session)) {
            return redirect()->back()->with('error', $error);
        }

        $items = $this->pairProductsWithQuantities($data);

        if ($items->isEmpty() && !$hour) {
            return redirect()->back()->with('error', 'Tidak ada item atau durasi yang dipilih.');
        }

        try {
            \DB::transaction(function () use ($order, $items, $hour, $session) {
                $this->addProducts($order, $items);

                if ($hour) {
                    $this->addHour($order, $hour, $session);
                }
            });
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            \Log::error('Tambah item order gagal', ['order' => $order->uuid, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Gagal menyimpan, silakan coba lagi.');
        }

        return redirect()->route('order.view', $order->uuid);
    }

    /**
     * Form mengirim product[] dan quantity[] sebagai dua array posisional, jadi
     * pasangannya HARUS lewat indeks yang di-post. Versi lama memakai indeks
     * hasil whereIn() yang urutannya ditentukan database, sehingga jumlah pesanan
     * bisa mendarat di produk yang salah dan produk duplikat hilang diam-diam.
     *
     * @return Collection<string,int> uuid produk => total qty
     */
    private function pairProductsWithQuantities(array $data): Collection
    {
        $items = collect();

        foreach ($data['product'] ?? [] as $index => $productUuid) {
            if (blank($productUuid)) {
                continue;
            }

            $quantity = (int) ($data['quantity'][$index] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            // Baris duplikat untuk produk yang sama dijumlahkan, bukan ditimpa.
            $items[$productUuid] = ($items[$productUuid] ?? 0) + $quantity;
        }

        return $items;
    }

    private function addProducts(Order $order, Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $products = Product::whereIn('uuid', $items->keys())->get()->keyBy('uuid');

        foreach ($items as $productUuid => $quantity) {
            $product = $products->get($productUuid);

            if (!$product) {
                throw new \DomainException('Produk tidak ditemukan.');
            }

            $order->orderItems()->create([
                'product_uuid' => $product->uuid,
                'quantity' => $quantity,
                'price' => $product->price * $quantity,
            ]);
        }
    }

    private function validateHourAgainstSession(Hour $hour, ?ActiveOrder $session): ?string
    {
        if (!$session) {
            return null;
        }

        if ($hour->type === 'free time' && $session->hour_type === 'regular' && $session->end_at->isFuture()) {
            return 'Waktu belum habis';
        }

        if ($hour->type === 'regular' && $session->hour_type === 'free time') {
            return 'Main bebas belum habis';
        }

        return null;
    }

    private function addHour(Order $order, Hour $hour, ?ActiveOrder $session): void
    {
        $mejaUuid = $this->resolveMejaUuid($order, $session);

        if ($hour->type === 'regular' && $session && $session->hour_type === 'regular') {
            // Perpanjangan dihitung dari waktu selesai yang masih berlaku. Kalau
            // blok sudah lewat, basisnya now() -- versi lama selalu memakai end_at
            // lama sehingga perpanjangan mendarat di masa lalu dan langsung
            // ditutup poll berikutnya (pelanggan bayar, tidak dapat waktu).
            $base = $session->end_at->isFuture() ? $session->end_at->copy() : now();

            $session->update([
                'hour' => $session->hour + $hour->hour,
                'is_active' => true,
                'end_at' => $base->addHours($hour->hour),
            ]);

            $target = $session;
        } else {
            $target = $order->activeOrders()->create([
                'product_uuid' => $mejaUuid,
                'hour' => $hour->hour,
                'is_active' => true,
                'started_at' => now(),
                'end_at' => now()->addHours($hour->hour),
                'hour_type' => $hour->type,
            ]);
        }

        $order->orderItems()->create([
            'product_uuid' => $mejaUuid,
            'quantity' => 1,
            'price' => $hour->price,
            'hour' => $hour->hour,
            'active_order_unique_id' => $target->unique_id,
        ]);
    }

    /**
     * Meja yang dipakai order ini. Versi lama memakai orderItems()->first() yang
     * bisa mengembalikan sebotol air mineral setelah minuman ditambahkan.
     */
    private function resolveMejaUuid(Order $order, ?ActiveOrder $session): string
    {
        $mejaUuid = $session?->product_uuid
            ?? $order->activeOrders()->orderByDesc('started_at')->value('product_uuid')
            ?? $order->orderItems()
                ->whereHas('product', fn ($query) => $query->where('type', 'billiard'))
                ->value('product_uuid');

        if (!$mejaUuid) {
            throw new \DomainException('Meja untuk order ini tidak ditemukan.');
        }

        return $mejaUuid;
    }

    public function edit($uuid)
    {
        $order = Order::findOrFail($uuid);
        $this->authorize('update', $order);

        return view('order-item.edit', [
            'order' => $order,
            'products' => Product::where('type', '!=', 'billiard')->get(),
            'hours' => Hour::all(),
        ]);
    }

    /**
     * Membatalkan satu baris order.
     *
     * Baris waktu biliar TIDAK boleh dibatalkan kasir. Baris itu mewakili waktu
     * yang benar-benar sudah dimainkan; menghapusnya membuat struk hanya berisi
     * minuman dan selisihnya bisa masuk kantong. Hanya admin yang boleh, dan
     * setiap pembatalan wajib beralasan serta tercatat di log aktivitas.
     */
    public function destroy($uuid, Request $request)
    {
        $orderItem = OrderItem::with('order', 'product')->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $orderItem->order);

        abort_if(
            $orderItem->order->is_paid,
            403,
            'Order sudah lunas, itemnya tidak bisa diubah lagi.'
        );

        abort_if(
            $orderItem->isBarisWaktu() && auth()->user()->role !== 'admin',
            403,
            'Baris waktu meja hanya bisa dibatalkan admin.'
        );

        $data = $request->validate([
            'void_reason' => 'required|string|min:4|max:255',
        ], [], ['void_reason' => 'alasan pembatalan']);

        // Alasan dan pelakunya disimpan SEBELUM soft delete, supaya baris yang
        // dibatalkan tetap bisa dipertanggungjawabkan.
        $orderItem->forceFill([
            'void_reason' => $data['void_reason'],
            'voided_by_uuid' => auth()->id(),
        ])->save();

        $orderItem->delete();

        \Log::channel('daily')->info(sprintf(
            'Item order dibatalkan: %s %s (order %s) oleh %s - alasan: %s',
            $orderItem->product?->name ?? '?',
            rupiah((int) $orderItem->price),
            $orderItem->order->order_number,
            auth()->user()->name,
            $data['void_reason']
        ));

        return redirect()->back()->with('status', 'Item dibatalkan.');
    }
}
