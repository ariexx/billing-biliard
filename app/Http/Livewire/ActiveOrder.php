<?php

namespace App\Http\Livewire;

use Livewire\Component;

class ActiveOrder extends Component
{
    public function render()
    {
        $activeOrder = \App\Models\ActiveOrder::whereIsActive(true)->get();
        return view('livewire.active-order', compact('activeOrder'));
    }
}
