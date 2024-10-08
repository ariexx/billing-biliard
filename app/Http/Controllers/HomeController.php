<?php

namespace App\Http\Controllers;

use App\DataTables\OrdersDataTable;
use App\Models\OrderItem;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function orderHistory(OrdersDataTable $table)
    {
        $totalOrder = auth()?->user()?->orders()?->whereDate('created_at', today())?->count();
        //get total income this day
        $totalIncome = OrderItem::query()?->whereDate('created_at', today())?->whereHas('product', function ($query) {
            $query->where('type', 'billiard');
        })->whereHas('order', function ($query) {
            $query->where('user_uuid', auth()->id());
        })->sum('price');
        return $table->render('livewire.order-history', [
            'totalOrder' => $totalOrder,
            'totalIncome' => $totalIncome
        ]);
    }

    public function orderHistoryDrinks(OrdersDataTable $table)
    {
        //get total order type of drink
        $totalOrder = OrderItem::query()?->whereDate('created_at', today())->whereHas('product', function ($query) {
            $query->where('type', 'drink');
        })->count();
        //get total income this day
        //get total income by type of drink
        $totalIncome = OrderItem::query()?->whereDate('created_at', today())->whereHas('product', function ($query) {
            $query->where('type', 'drink');
        })->sum('price');

        $orderItems = OrderItem::query()?->whereDate('created_at', today())->whereHas('product', function ($query) {
            $query->where('type', 'drink');
        })->get();

        //get quantity of each drink
        $drinkAndTotal = [];
        foreach ($orderItems as $orderItem) {
            if (array_key_exists($orderItem->product->name, $drinkAndTotal)) {
                $drinkAndTotal[$orderItem->product->name] += $orderItem->quantity;
            } else {
                $drinkAndTotal[$orderItem->product->name] = $orderItem->quantity;
            }
        }


        return $table->render('livewire.order-history-drinks', [
            'totalOrder' => $totalOrder,
            'totalIncome' => $totalIncome,
            'orderItems' => $orderItems,
            'drinkAndTotal' => $drinkAndTotal
        ]);
    }
}
