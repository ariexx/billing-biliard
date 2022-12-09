<?php

namespace App\Http\Livewire;

use App\Models\ActiveOrder as ModelsActiveOrder;
use Livewire\Component;

class ActiveOrder extends Component
{
    public function render()
    {
        $activeOrder = \App\Models\ActiveOrder::whereIsActive(true)->get();
        return view('livewire.active-order', compact('activeOrder'));
    }

    public function stopTimer($orderUuid)
    {
        /**
         * TODO
         * 1. Hitung waktu yang sudah berjalan
         * 2. Hitung harga yang harus dibayar
         * 3. Update data order
         * TODO :
         * 1. Waktu baru ditambahkan jika waktu sebelumnya sudah habis (belum fix)
         */
        $order = ModelsActiveOrder::whereOrderUuid($orderUuid)->first();
        $calculatePrice = $order->updated_at->diffInMinutes(now()) * 750;
        dd(rupiah($calculatePrice));
    }
}
