<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Product as ProductModel;
class Product extends Component
{
    public function render()
    {
        $billiardProducts = ProductModel::with('hours')->whereType('billiard')->get();
        return view('livewire.product', compact('billiardProducts'));
    }
}
