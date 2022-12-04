<?php

namespace App\Http\Livewire;

use Livewire\Component;

class OrderHistory extends Component
{
    public $orders;

    public function render()
    {
        return view('livewire.order-history');
    }
}
