<?php

namespace App\Http\Livewire;

use App\Models\ActiveOrder as ModelsActiveOrder;
use App\Models\OrderItem;
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
        $activeOrder = \App\Models\ActiveOrder::whereIsActive(true)->get();
        return view('livewire.active-order', compact('activeOrder'));
    }

    public function stopTimer($orderUuid)
    {
        \DB::beginTransaction();
        try {

            $order = ModelsActiveOrder::whereOrderUuid($orderUuid)->firstOrFail();
            $orderItem = OrderItem::whereOrderUuid($orderUuid)->firstOrFail();
            $calculatePrice = $order->updated_at->diffInMinutes(now()) * $orderItem->price;

            $order->update([
                'is_active' => false,
                'end_at' => now()
            ]);

            //Update the price in order_items
            $orderItem->update([
                'price' => $calculatePrice,
            ]);

            $this->alert('success', 'Order Selesai');
            \DB::commit();
        }catch (\Exception $e) {
            \DB::rollBack();
            $this->alert('error', 'Order tidak ditemukan');
        }
    }

    public function habiskanWaktu($orderUuid)
    {
        $activeOrder = ModelsActiveOrder::where('order_uuid', $orderUuid)->first();
        if (!$activeOrder) {
            $this->alert('error', 'Order tidak ditemukan');
            return;
        }

        $activeOrder->update([
            'is_active' => false,
            'end_at' => now()
        ]);

        $this->alert('success', 'Aksi berhasil');
    }
}
