<?php

namespace App\Http\Livewire;

use Livewire\Component;

class OrderHistory extends Component
{
    public $orders;
    public $totalOrder;

    public function render()
    {
        $this->totalOrder = auth()?->user()?->orders()?->whereDate('created_at', today())?->count();
        return view('livewire.order-history');
    }
}
