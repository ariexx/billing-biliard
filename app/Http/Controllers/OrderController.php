<?php

namespace App\Http\Controllers;

use App\Models\ActiveOrder;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function view($uuid)
    {
        $order = Order::with('orderItems.activeOrder', 'orderItems.product', 'user', 'payment')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $order);

        return view('order.view', compact('order'));
    }

    /**
     * Menandai order lunas dan mencatat metode pembayarannya.
     *
     * Sebelum ini metode bayar dipatok Payment::first() saat meja dibuka, jadi
     * kolom "Metode Bayar" di semua laporan selalu menampilkan nilai yang sama
     * dan kas tidak bisa direkonsiliasi.
     */
    public function bayar($uuid, Request $request)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $order);

        $data = $request->validate([
            'payment_uuid' => 'required|exists:payments,uuid',
        ]);

        if ($order->is_paid) {
            return redirect()->back()->with('error', 'Order ini sudah ditandai lunas.');
        }

        if ($order->currentSession()) {
            return redirect()->back()->with('error', 'Masih ada sesi meja yang berjalan. Selesaikan dulu.');
        }

        $order->update([
            'payment_uuid' => $data['payment_uuid'],
            'paid_at' => now(),
            'paid_by_uuid' => auth()->id(),
        ]);

        \Log::channel('daily')->info(sprintf(
            'Order lunas: %s - %s - %s oleh %s',
            $order->order_number,
            rupiah((int) $order->total),
            $order->payment?->name,
            auth()->user()->name
        ));

        return redirect()->route('order.view', $order->uuid)->with('status', 'Order ditandai lunas.');
    }

    public function pindahMeja($uuid)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $order);

        return view('order.pindah-meja', [
            'order' => $order,
            'tables' => $this->mejaTersedia(),
        ]);
    }

    public function pindahMejaPost($orderUuid, Request $request)
    {
        $order = Order::where('uuid', $orderUuid)->firstOrFail();
        $this->authorize('update', $order);

        $data = $request->validate([
            'table_uuid' => 'required|exists:products,uuid',
        ]);

        try {
            \DB::transaction(function () use ($order, $data) {
                $session = $order->currentSession();

                if (!$session) {
                    throw new \DomainException('Order ini tidak punya sesi meja yang aktif.');
                }

                $tujuan = Product::where('uuid', $data['table_uuid'])
                    ->where('type', 'billiard')
                    ->firstOrFail();

                // Meja tujuan bisa saja diambil kasir lain antara form dibuka dan
                // disubmit, jadi ketersediaannya dicek ulang di sini sambil dikunci.
                $terpakai = ActiveOrder::where('product_uuid', $tujuan->uuid)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->exists();

                if ($terpakai) {
                    throw new \DomainException('Meja tujuan sedang digunakan.');
                }

                $mejaLama = $session->product_uuid;

                // Semua baris meja pada order ini ikut pindah. Versi lama hanya
                // memindahkan satu order item, sehingga order yang pernah
                // diperpanjang terbelah ke dua meja di struk.
                $order->orderItems()
                    ->where('product_uuid', $mejaLama)
                    ->update(['product_uuid' => $tujuan->uuid]);

                $order->activeOrders()
                    ->where('product_uuid', $mejaLama)
                    ->update(['product_uuid' => $tujuan->uuid]);
            });
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            \Log::error('Pindah meja gagal', ['order' => $order->uuid, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Gagal memindahkan meja, silakan coba lagi.');
        }

        return redirect()->route('order.view', ['uuid' => $order->uuid]);
    }

    /**
     * Meja biliar yang tidak sedang dipakai sesi aktif mana pun.
     */
    private function mejaTersedia()
    {
        return Product::where('type', 'billiard')
            ->whereNotIn('uuid', ActiveOrder::getActiveOrderProductUuid())
            ->get();
    }
}
