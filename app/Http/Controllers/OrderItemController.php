<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Request;

class OrderItemController extends Controller
{
    public function edit($uuid)
    {
        return view('order-item.edit', [
            'order' => Order::findOrFail($uuid),
        ]);
    }

    public function destroy($uuid)
    {
        $orderItem = OrderItem::where('uuid', $uuid)->firstOrFail();
        $orderItem->delete();
        return redirect()->back();
    }
}
