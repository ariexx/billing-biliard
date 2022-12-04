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
        //TODO not validated yet
        if (!empty($request['product'])) {
            $product = Product::where('uuid', $request['product'])->first();
            $order = Order::whereUuid($uuid)->first();
            $order->orderItems()->create([
                'product_uuid' => $product->uuid,
                'quantity' => 1,
                'price' => $product->price,
            ]);
        }

        if (!empty($request['hour'])) {
            $hour = Hour::whereUuid($request['hour'])->firstOrFail();
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
