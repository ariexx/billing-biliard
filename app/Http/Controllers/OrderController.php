<?php

namespace App\Http\Controllers;

use App\Models\ActiveOrder;
use App\Models\Order;
use App\Models\Product;
use Request;

class OrderController extends Controller
{

    public function view($uuid)
    {
        return view('order.view', [
            'order' => Order::with('orderItems.activeOrder')->where('uuid', $uuid)->firstOrFail(),
        ]);
    }

    public function edit($uuid)
    {
        return view('order.edit', [
            'order' => Order::where('uuid', $uuid)->firstOrFail(),
        ]);
    }

    public function pindahMeja($uuid)
    {
        $orderUuidNotInUse = ActiveOrder::getActiveOrderProductUuid();
        $orderUuidNotInUse[] = $uuid;

        //select product where uuid not in $orderUuidNotInUse
        $productNotInUse = Product::whereNotIn('uuid', $orderUuidNotInUse)->where("type", "billiard")->get();

        return view('order.pindah-meja', [
            'order' => Order::where('uuid', $uuid)->firstOrFail(),
            'tables' => $productNotInUse,
        ]);
    }

    public function pindahMejaPost($orderUuid)
    {
        \DB::beginTransaction();
        try {
            //get order where uuid
            $order = Order::with('activeOrder')->where('uuid', $orderUuid)->firstOrFail();
            //select order item where order uuid and product uuid
            $orderItem = $order->orderItems()->where('product_uuid', $order->activeOrder->product_uuid)->firstOrFail();
            //update the product uuid with new product uuid
            $orderItem->product_uuid = Request::get('table_uuid');
            //update the active order with new product uuid
            $order->activeOrder->product_uuid = Request::get('table_uuid');
            //save the order item
            $orderItem->save();
            //save the active order
            $order->activeOrder->save();
            //commit
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('order.view', ['uuid' => $order->uuid]);
    }
}
