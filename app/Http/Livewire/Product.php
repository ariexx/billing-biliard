<?php

namespace App\Http\Livewire;

use App\Models\ActiveOrder;
use App\Models\Hour;
use App\Models\Order;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use App\Models\Product as ProductModel;
class Product extends Component
{
    use LivewireAlert;

    public $selectedHour;
    public $hourUuid;

    public function render()
    {
        $billiardProducts = ProductModel::with('hours')->whereType('billiard')
        ->orderBy('created_at', 'asc')->get();
        return view('livewire.product', compact('billiardProducts'));
    }

    public function updatedSelectedHour($hourId)
    {
        $this->hourUuid = Hour::find($hourId)->uuid;
    }

    public function saveOrder($productId, $hoursId)
    {
        //check if there is an active order
        $activeOrder = ActiveOrder::whereProductUuid($productId)->whereIsActive(true)->first();
        if ($activeOrder) {
            $this->alert('error', 'Meja yang dipilih sedang digunakan!');
            return;
        }

        $product = ProductModel::findOrFail($productId);
        $hour = Hour::findOrFail($hoursId);
        $order = Order::create([
            'payment_uuid' => '8f427bf1-1553-4509-b1ee-967e3f639a90',
        ]);

        $order->activeOrder()->create([
            'order_uuid' => $order->uuid,
            'product_uuid' => $product->uuid,
            'hour' => $hour->hour,
            'is_active' => true,
            'started_at' => now(),
            'end_at' => now()->addHours($hour->hour),
        ]);

        $saveToOrderItem = $order->orderItems()->create([
            'product_uuid' => $product->uuid,
            'hour_uuid' => $hoursId,
            'quantity' => 1,
            'price' => $hour->price,
        ]);

        if ($saveToOrderItem) {
            $this->alert('success', 'Order has been saved!');
        }else{
            $this->alert('error', 'Something went wrong!');
            \Log::error('Something went wrong!', ['saveToOrderItem' => $saveToOrderItem]);
        }
    }
}
