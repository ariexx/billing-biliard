<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Hour;
use App\Models\Order;
use App\Models\OrderItem;

class OrderItemController extends Controller
{

    public function update($uuid, \Illuminate\Http\Request $request)
    {
        $only = $request->only([
            'product',
            'hour',
            'quantity',
        ]);
        // Validate the request
        $validator = \Validator::make($only, [
            'product' => 'array',
            'product.*' => 'nullable|exists:products,uuid',
            'hour' => 'nullable|exists:hours,uuid',
            'quantity' => 'array',
            'quantity.*' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ($only['product']['0'] != null) {
            $product = Product::whereIn('uuid', $only['product'])->get();
            $order = Order::whereUuid($uuid)->first();
            foreach ($product as $item => $value) {
                $order->orderItems()->create([
                    'product_uuid' => $value->uuid,
                    'quantity' => $only['quantity'][$item],
                    'price' => $value->price * $only['quantity'][$item],
                ]);
            }
        }

        if (!empty($only['hour'])) {
            $hour = Hour::whereUuid($only['hour'])->firstOrFail();
            $order = Order::whereUuid($uuid)->firstOrFail();
            $order->orderItems()->create([
                'product_uuid' => $order->orderItems()->first()->product_uuid,
                'hour_uuid' => $hour->uuid,
                'quantity' => 1,
                'price' => $hour->price,
            ]);
            $order->activeOrder()->update([
                'hour' => $order->activeOrder->hour + $hour->hour,
                'is_active' => true,
                'started_at' => $order->activeOrder->started_at,
                'end_at' => $order->activeOrder->end_at->addHours($hour->hour),
            ]);

            return redirect()->route('order.view', $uuid);
        }

        return redirect()->route('order.view', $uuid);
    }


    public function edit($uuid)
    {
        //merge product and hours
        $product = Product::where('type', '!=', 'billiard')->get();
        $hour = Hour::all();
        return view('order-item.edit', [
            'order' => Order::findOrFail($uuid),
            'products' => $product,
            'hours' => $hour
        ]);
    }

    public function destroy($uuid)
    {
        $orderItem = OrderItem::where('uuid', $uuid)->firstOrFail();
        $orderItem->delete();
        return redirect()->back();
    }
}
