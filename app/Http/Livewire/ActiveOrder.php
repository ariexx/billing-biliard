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

    public function stopTimer($orderUuid, $uniqueUuid)
    {
        \DB::beginTransaction();
        try {
            $order = ModelsActiveOrder::where('order_uuid', $orderUuid)
                ->where('unique_id', $uniqueUuid)
                ->firstOrFail();
            $orderItem = OrderItem::where('order_uuid', $orderUuid)
                ->where('active_order_unique_id', $uniqueUuid)
                ->firstOrFail();
            $timePlayed = $order->started_at->diffInMinutes(now());
            $calculatePrice = $timePlayed * $orderItem->price;

            $order->update([
                'is_active' => false,
                'end_at' => now()
            ]);

            $orderItem->update([
                'price' => $calculatePrice,
            ]);

            \DB::commit();
            $this->alert('success', 'Order Selesai');
        } catch (\Exception $e) {
            \DB::rollBack();
            $this->alert('error', 'Order tidak ditemukan');
        }
    }

    public function habiskanWaktu($uniqueUuid)
    {
        $activeOrder = ModelsActiveOrder::where('unique_id', $uniqueUuid)
            ->first();
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
