<?php

namespace App\Http\Livewire;

use App\Models\ActiveOrder;
use App\Models\Hour;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product as ProductModel;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;

class Product extends Component
{
    use LivewireAlert;

    public $selectedHours = [];

    public function render()
    {
        $billiardProducts = ProductModel::with(['hours' => fn ($query) => $query->orderBy('hour')])
            ->whereType('billiard')
            ->orderBy('created_at', 'asc')
            ->get();

        return view('livewire.product', compact('billiardProducts'));
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

        $product = ProductModel::find($productId);
        if (!$product) {
            $this->alert('error', 'Meja tidak ditemukan.');
            return;
        }

        $hour = Hour::find($this->selectedHours[$productId]);
        if (!$hour) {
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

                $order = Order::create([
                    'payment_uuid' => $payment->uuid,
                ]);

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

    // For resetting form
    public function resetForm()
    {
        $this->selectedHours = [];
    }
}
