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
        // Get the authenticated user ID once
        $userId = auth()->id();

        // Use a single query with joins instead of nested whereHas
        $totalIncome = OrderItem::query()
            ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
            ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereDate('order_items.created_at', today())
            ->where('products.type', 'billiard')
            ->where('orders.user_uuid', $userId)
            ->sum('order_items.price');

        // Get count more efficiently
        $totalOrder = \DB::table('orders')
            ->where('user_uuid', $userId)
            ->whereDate('created_at', today())
            ->count();

        // Consider adding cache for frequently accessed data
        // Cache::remember('order_history_'.$userId, now()->addMinutes(5), function() use ($totalOrder, $totalIncome) {
        //     return ['totalOrder' => $totalOrder, 'totalIncome' => $totalIncome];
        // });

        return $table->render('livewire.order-history', [
            'totalOrder' => $totalOrder,
            'totalIncome' => $totalIncome
        ]);
    }

    public function orderHistoryDrinks(OrdersDataTable $table)
    {
        // Base query for drink items with proper joins
        $baseQuery = OrderItem::query()
            ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
            ->whereDate('order_items.created_at', today())
            ->where('products.type', 'drink');

        // Get counts and totals in a more efficient way
        $totalOrder = (clone $baseQuery)->count();
        $totalIncome = (clone $baseQuery)->sum('order_items.price');

        // Get drink quantities grouped by product name - doing this in SQL is much faster
        $drinkAndTotal = \DB::table('order_items')
            ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
            ->select('products.name', \DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->whereDate('order_items.created_at', today())
            ->where('products.type', 'drink')
            ->groupBy('products.name')
            ->pluck('total_quantity', 'name')
            ->toArray();

        // We still need the order items for other display purposes
        $orderItems = (clone $baseQuery)
            ->with('product') // Eager load product to prevent N+1 problem
            ->get();

        // Optional caching if needed:
        // $cacheKey = 'drink_history_' . today()->format('Y-m-d');
        // $data = Cache::remember($cacheKey, now()->addHours(1), function() use ($totalOrder, $totalIncome, $orderItems, $drinkAndTotal) {
        //     return compact('totalOrder', 'totalIncome', 'orderItems', 'drinkAndTotal');
        // });

        return $table->render('livewire.order-history-drinks', [
            'totalOrder' => $totalOrder,
            'totalIncome' => $totalIncome,
            'orderItems' => $orderItems,
            'drinkAndTotal' => $drinkAndTotal
        ]);
    }
}
