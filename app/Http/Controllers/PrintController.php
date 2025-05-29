<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function __invoke(Request $request)
    {
        $only = $request->only(['order_uuid']);
        $order = Order::findOrFail($only['order_uuid']);

        try {
            // Update print count
            $order->update(['print_count' => $order->print_count + 1]);

            // Log printing activity
            \Log::channel('daily')->info(
                "Print Success: Order Number : {$order->order_number} - " .
                "Total Print : {$order->print_count} - " .
                "Printed At : " . now()->format("Y-m-d H:i:s") . " - " .
                "Printed By : " . auth()->user()->name
            );

            // Return the print view
            return view('order.print-receipt', compact('order'));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
