<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'order_uuid' => 'required|exists:orders,uuid',
        ]);

        $order = Order::with('orderItems.product', 'user')
            ->where('uuid', $data['order_uuid'])
            ->firstOrFail();

        $this->authorize('print', $order);

        $order->update(['print_count' => $order->print_count + 1]);

        \Log::channel('daily')->info(
            "Print Success: Order Number : {$order->order_number} - " .
            "Total Print : {$order->print_count} - " .
            "Printed At : " . now()->format("Y-m-d H:i:s") . " - " .
            "Printed By : " . auth()->user()->name
        );

        return view('order.print-receipt', compact('order'));
    }
}
