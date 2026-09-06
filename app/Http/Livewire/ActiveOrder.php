<?php

namespace App\Http\Livewire;

use App\Models\ActiveOrder as ModelsActiveOrder;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;

class ActiveOrder extends Component
{
    use LivewireAlert;

    public $orderSuccess;

    public function resetData()
    {
    }

    public function render()
    {
        // orderItem ikut di-eager-load: tagihan berjalan sesi main bebas dibaca
        // dari harga per menit yang tersimpan di sana.
        $activeOrder = \App\Models\ActiveOrder::with('product', 'orderItem')
            ->whereIsActive(true)
            ->get();

        return view('livewire.active-order', compact('activeOrder'));
    }

    /**
     * Menutup sesi main bebas dan mengubah tarif per menit yang tersimpan di
     * order item menjadi total tagihan.
     *
     * Filter is_active + lockForUpdate WAJIB: tanpa itu klik kedua menghitung
     * ulang menit x harga di mana harga sudah menjadi total, sehingga tagihan
     * berlipat ganda. Dua tab yang terbuka bersamaan sudah cukup untuk memicunya
     * karena kartu meja baru hilang pada poll 10 detik berikutnya.
     */
    public function stopTimer($orderUuid, $uniqueUuid)
    {
        try {
            \DB::transaction(function () use ($orderUuid, $uniqueUuid) {
                $order = ModelsActiveOrder::where('order_uuid', $orderUuid)
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

                $order->update([
                    'is_active' => false,
                    'end_at' => now(),
                ]);

                $orderItem->update([
                    'price' => $timePlayed * $orderItem->price,
                ]);
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
        $activeOrder = ModelsActiveOrder::where('unique_id', $uniqueUuid)
            ->where('is_active', true)
            ->first();

        if (!$activeOrder) {
            $this->alert('error', 'Sesi sudah selesai atau tidak ditemukan.');
            return;
        }

        if ($activeOrder->hour_type !== 'regular') {
            $this->alert('error', 'Sesi main bebas harus ditutup lewat tombol Selesai.');
            return;
        }

        $activeOrder->update([
            'is_active' => false,
            'end_at' => now()
        ]);

        $this->alert('success', 'Aksi berhasil');
    }
}
