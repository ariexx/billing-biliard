<?php

namespace App\Http\Livewire;

use App\Models\ActiveOrder;
use App\Models\Hour;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;

/**
 * Satu grid untuk seluruh meja: kosong maupun sedang dipakai.
 *
 * Sebelumnya ini dua komponen terpisah (Product dan ActiveOrder), sehingga satu
 * meja muncul dua kali di layar dan kasir harus menggulir untuk melihat keadaan
 * lengkap. Menggabungkannya juga sebuah keharusan teknis: satu kartu memerlukan
 * aksi "Mulai" maupun "Selesai", dan aksi Livewire tidak bisa lintas komponen.
 */
class MejaGrid extends Component
{
    use LivewireAlert;

    /** Durasi terpilih per meja, dikunci dengan uuid produk. */
    public $selectedHours = [];

    /** Menit tersisa saat kartu mulai diberi peringatan oranye. */
    public const AMBANG_PERINGATAN = 10;

    public function render()
    {
        $meja = Product::with(['hours' => fn ($q) => $q->orderBy('hour')])
            ->whereType('billiard')
            ->orderBy('name')
            ->get();

        // orderItem ikut di-eager-load: tagihan berjalan sesi main bebas dibaca
        // dari tarif per menit yang tersimpan di sana.
        $sesiPerMeja = ActiveOrder::with('orderItem')
            ->where('is_active', true)
            ->get()
            ->keyBy('product_uuid');

        return view('livewire.meja-grid', compact('meja', 'sesiPerMeja'));
    }

    public function saveOrder($productId)
    {
        $payment = Payment::where('is_active', true)->first();

        if ($payment === null) {
            $this->alert('error', 'Belum ada metode pembayaran aktif, hubungi admin.');
            return;
        }

        if (empty($this->selectedHours[$productId])) {
            $this->alert('error', 'Silakan pilih durasi bermain terlebih dahulu.');
            return;
        }

        $product = Product::find($productId);
        if (! $product) {
            $this->alert('error', 'Meja tidak ditemukan.');
            return;
        }

        $hour = Hour::find($this->selectedHours[$productId]);
        if (! $hour) {
            $this->alert('error', 'Durasi yang dipilih tidak ditemukan.');
            return;
        }

        try {
            // Cek "meja terpakai?" dan insert-nya harus satu transaksi dengan lock.
            // Tanpa ini dua kasir yang mengklik meja sama dalam detik yang sama
            // sama-sama lolos pengecekan dan membuat dua order untuk satu meja.
            \DB::transaction(function () use ($product, $hour, $payment) {
                $sedangDipakai = ActiveOrder::where('product_uuid', $product->uuid)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->exists();

                if ($sedangDipakai) {
                    throw new \DomainException('Meja yang dipilih sedang digunakan!');
                }

                $order = Order::create(['payment_uuid' => $payment->uuid]);

                $activeOrder = $order->activeOrder()->create([
                    'order_uuid' => $order->uuid,
                    'product_uuid' => $product->uuid,
                    'hour' => $hour->hour,
                    'is_active' => true,
                    'started_at' => now(),
                    'end_at' => now()->addHours($hour->hour),
                    'hour_type' => $hour->type,
                ]);

                $activeOrder->orderItem()->create([
                    'order_uuid' => $order->uuid,
                    'product_uuid' => $product->uuid,
                    'quantity' => 1,
                    'price' => $hour->price,
                    'hour' => $hour->hour,
                    'active_order_unique_id' => $activeOrder->unique_id,
                ]);
            });
        } catch (\DomainException $e) {
            $this->alert('error', $e->getMessage());
            return;
        } catch (\Throwable $e) {
            \Log::error('saveOrder gagal', ['product' => $productId, 'error' => $e->getMessage()]);
            $this->alert('error', 'Gagal menyimpan order, silakan coba lagi.');
            return;
        }

        $this->alert('success', 'Order berhasil disimpan!');
        $this->resetForm();
    }

    /**
     * Menutup sesi main bebas dan mengubah tarif per menit yang tersimpan di
     * order item menjadi total tagihan.
     *
     * Filter is_active + lockForUpdate WAJIB: tanpa itu klik kedua menghitung
     * ulang menit x harga di mana harga sudah menjadi total, sehingga tagihan
     * berlipat ganda. Dua tab yang terbuka bersamaan sudah cukup memicunya
     * karena kartu meja baru hilang pada poll 10 detik berikutnya.
     */
    public function stopTimer($orderUuid, $uniqueUuid)
    {
        try {
            \DB::transaction(function () use ($orderUuid, $uniqueUuid) {
                $order = ActiveOrder::where('order_uuid', $orderUuid)
                    ->where('unique_id', $uniqueUuid)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->hour_type !== 'free time') {
                    throw new \DomainException('Sesi ini bukan main bebas.');
                }

                $orderItem = OrderItem::where('order_uuid', $orderUuid)
                    ->where('active_order_unique_id', $uniqueUuid)
                    ->lockForUpdate()
                    ->firstOrFail();

                // $orderItem->price masih berisi tarif per menit sampai baris ini.
                $timePlayed = $order->started_at->diffInMinutes(now());

                $order->update(['is_active' => false, 'end_at' => now()]);
                $orderItem->update(['price' => $timePlayed * $orderItem->price]);
            });

            $this->alert('success', 'Order Selesai');
        } catch (\DomainException $e) {
            $this->alert('error', $e->getMessage());
        } catch (ModelNotFoundException $e) {
            $this->alert('error', 'Sesi sudah selesai atau tidak ditemukan.');
        } catch (\Throwable $e) {
            \Log::error('stopTimer gagal', ['order' => $orderUuid, 'error' => $e->getMessage()]);
            $this->alert('error', 'Gagal menutup sesi, silakan coba lagi.');
        }
    }

    /**
     * Mengakhiri blok jam reguler lebih awal. Blok reguler dibayar di muka
     * sehingga harganya tidak dihitung ulang.
     *
     * Guard hour_type wajib: method Livewire bisa dipanggil langsung ke endpoint
     * /livewire/message, dan tanpa guard ini sesi main bebas bisa ditutup lewat
     * jalur ini tanpa pernah ditagih.
     */
    public function habiskanWaktu($uniqueUuid)
    {
        $activeOrder = ActiveOrder::where('unique_id', $uniqueUuid)
            ->where('is_active', true)
            ->first();

        if (! $activeOrder) {
            $this->alert('error', 'Sesi sudah selesai atau tidak ditemukan.');
            return;
        }

        if ($activeOrder->hour_type !== 'regular') {
            $this->alert('error', 'Sesi main bebas harus ditutup lewat tombol Selesai.');
            return;
        }

        $activeOrder->update(['is_active' => false, 'end_at' => now()]);

        $this->alert('success', 'Aksi berhasil');
    }

    public function resetForm()
    {
        $this->selectedHours = [];
    }
}
