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
        /**
         * TODO
         * 1. Hitung waktu yang sudah berjalan
         * 2. Hitung harga yang harus dibayar
         * 3. Update data order dan harga di order_items dan set is_active = false di active_orders
         * TODO :
         * 1. Waktu baru ditambahkan jika waktu sebelumnya sudah habis (belum fix)
         */
        $order = ModelsActiveOrder::whereOrderUuid($orderUuid)->whereUniqueId($uniqueId)->first();
        $getPriceByOrderItem = OrderItem::whereOrderUuid($orderUuid)->whereActiveOrderUniqueId($uniqueId)
            ->firstOrFail()->price;
        $calculatePrice = $order->updated_at->diffInMinutes(now()) * $getPriceByOrderItem;

        //Update the start at and end at active_orders
        $order->update([
            'is_active' => false,
            'end_at' => now(),
        ]);

        //Update the price in order_items
        OrderItem::whereOrderUuid($orderUuid)->whereActiveOrderUniqueId($uniqueId)->update([
            'price' => $calculatePrice,
        ]);

        $this->alert('success', 'Order Selesai');
    }
}
