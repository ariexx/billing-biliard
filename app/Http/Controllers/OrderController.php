<?php

namespace App\Http\Controllers;

use App\Models\Order;
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
}
