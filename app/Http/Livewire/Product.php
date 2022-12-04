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
        $payment = Payment::first()->uuid;

        if ($payment == null) {
            $this->alert('error', 'Please add payment method first!');
            return;
        }

        //check if there is an active order
        $activeOrder = ActiveOrder::whereProductUuid($productId)->whereIsActive(true)->first();
        if ($activeOrder) {
            $this->alert('error', 'Meja yang dipilih sedang digunakan!');
            return;
        }

        if (!$this->hourUuid) {
            $this->alert('error', 'Please select a time');
            return;
        }

        $product = ProductModel::find($productId);

        if (!$product) {
            $this->alert('error', 'Product not found');
            return;
        }

        $hour = Hour::find($hoursId);
        if (!$hour) {
            $this->alert('error', 'Hour not found');
            return;
        }

        $order = Order::create([
            'payment_uuid' => $payment,
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
        } else {
            $this->alert('error', 'Something went wrong!');
            \Log::error('Something went wrong!', ['saveToOrderItem' => $saveToOrderItem]);
        }
    }
}
