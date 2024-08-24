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
        $billiardProducts = ProductModel::with('hours')->whereType('billiard')
            ->orderBy('created_at', 'asc')->get();
        return view('livewire.product', compact('billiardProducts'));
    }

    public function updatedSelectedHour($productId, $hourId)
    {
        $this->selectedHours[$productId] = $hourId;
    }

    public function saveOrder($productId)
    {
        $payment = Payment::first()->uuid;

        if ($payment == null) {
            $this->alert('error', 'Please add payment method first!');
            return;
        }

        // Check if there is an active order
        $activeOrder = ActiveOrder::whereProductUuid($productId)->whereIsActive(true)->first();
        if ($activeOrder) {
            $this->alert('error', 'Meja yang dipilih sedang digunakan!');
            return;
        }

        if (empty($this->selectedHours[$productId])) {
            $this->alert('error', 'Please select hours!');
            return;
        }

        $product = ProductModel::find($productId);
        if (!$product) {
            $this->alert('error', 'Product not found');
            return;
        }

        $hour = Hour::find($this->selectedHours[$productId]);
        if (!$hour) {
            $this->alert('error', 'Hour not found');
            return;
        }

        $order = Order::create([
            'payment_uuid' => $payment,
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

        $saveToOrderItem = $activeOrder->orderItem()->create([
            'order_uuid' => $order->uuid,
            'product_uuid' => $product->uuid,
            'quantity' => 1,
            'price' => $hour->price,
            'hour' => $hour->hour,
            'active_order_unique_id' => $activeOrder->unique_id,
        ]);

        if ($saveToOrderItem) {
            $this->alert('success', 'Order has been saved!');
            // Reset form after success
            $this->resetForm();
        } else {
            $this->alert('error', 'Something went wrong!');
            \Log::error('Something went wrong!', ['saveToOrderItem' => $saveToOrderItem]);
        }
    }

    // For resetting form
    public function resetForm()
    {
        $this->selectedHours = [];
    }
}
