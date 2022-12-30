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

    public function render()
    {
        $activeOrder = \App\Models\ActiveOrder::whereIsActive(true)->get();
        return view('livewire.active-order', compact('activeOrder'));
    }

    public function stopTimer($orderUuid, $uniqueId)
    {
        $order = ModelsActiveOrder::whereUniqueId($uniqueId)->firstOrFail();
        $getPriceByOrderItem = OrderItem::whereActiveOrderUniqueId($uniqueId)
            ->firstOrFail()->price;
        $calculatePrice = $order->updated_at->diffInMinutes(now()) * $getPriceByOrderItem;

        $order->update([
            'is_active' => false,
            'end_at' => now()
        ]);

        //Update the price in order_items
        OrderItem::whereActiveOrderUniqueId($uniqueId)->update([
            'price' => $calculatePrice,
        ]);

        $this->alert('success', 'Order Selesai');
    }

    public function habiskanWaktu($uniqueId)
    {
        $activeOrder = ModelsActiveOrder::where('unique_id', $uniqueId)->first();
        if(!$activeOrder) {
            $this->alert('error', 'Order tidak ditemukan');
            return;
        }

        $activeOrder->update([
            'is_active' => false,
        ]);

        $this->alert('success', 'Aksi berhasil');
    }
}
