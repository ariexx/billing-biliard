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
        $data = $request->only(['product', 'hour', 'quantity']);

        $validator = \Validator::make($data, [
            'product' => 'array',
            'product.*' => 'nullable|exists:products,uuid',
            'hour' => 'nullable|exists:hours,uuid',
            'quantity' => 'array',
            'quantity.*' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $order = Order::whereUuid($uuid)->firstOrFail();

        if (!empty($data['product'][0])) {
            $products = Product::whereIn('uuid', $data['product'])->get();
            foreach ($products as $index => $product) {
                $order->orderItems()->create([
                    'product_uuid' => $product->uuid,
                    'quantity' => $data['quantity'][$index],
                    'price' => $product->price * $data['quantity'][$index],
                ]);
            }
        }

        if (!empty($data['hour'])) {
            $hour = Hour::whereUuid($data['hour'])->firstOrFail();
            $productUuid = $order->orderItems()->first()->product_uuid;

            if ($hour->type == 'free time') {
                $activeOrder = $order->activeOrder()->create([
                    'product_uuid' => $productUuid,
                    'hour' => $hour->hour,
                    'is_active' => 1,
                    'started_at' => now(),
                    'end_at' => now()->addHours($hour->hour),
                    'hour_type' => $hour->type,
                ]);

                $order->orderItems()->create([
                    'product_uuid' => $productUuid,
                    'active_order_unique_id' => $activeOrder->unique_id,
                    'quantity' => 1,
                    'price' => $hour->price,
                    'hour' => $hour->hour,
                ]);
            } elseif ($hour->type == 'regular') {
                $order->orderItems()->create([
                    'product_uuid' => $productUuid,
                    'quantity' => 1,
                    'price' => $hour->price,
                    'active_order_unique_id' => $order->activeOrder->unique_id,
                    'hour' => $hour->hour,
                    'hour_type' => $hour->type,
                ]);

                $order->activeOrder()->update([
                    'hour' => $order->activeOrder->hour + $hour->hour,
                    'is_active' => true,
                    'started_at' => $order->activeOrder->started_at,
                    'end_at' => $order->activeOrder->end_at->addHours($hour->hour),
                ]);
            } else {
                return redirect()->back()->with('error', 'Something went wrong');
            }

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
