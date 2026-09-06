<?php

namespace App\Http\Controllers;

use App\DataTables\OrdersDataTable;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // Order yang sesinya sudah selesai tapi belum ditandai lunas. Sebelum ada
        // ini, order yang belum dibayar tidak muncul di mana pun setelah kartu
        // mejanya hilang dari dashboard.
        $belumDibayar = \App\Models\Order::belumLunas()
            ->with('orderItems.product')
            ->whereDoesntHave('activeOrders', fn ($query) => $query->where('is_active', true))
            ->when(
                auth()->user()?->role !== 'admin',
                fn ($query) => $query->where('user_uuid', auth()->id())
            )
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('home', compact('belumDibayar'));
    }

    public function orderHistory(OrdersDataTable $table)
    {
        $userId = auth()->id();
        [$dari, $sampai] = $this->rentangHariIni();

        $totalIncome = OrderItem::query()
            ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
            ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('order_items.created_at', [$dari, $sampai])
            ->where('products.type', 'billiard')
            ->where('orders.user_uuid', $userId)
            ->whereNull('orders.deleted_at')
            ->sum('order_items.price');

        $totalOrder = DB::table('orders')
            ->where('user_uuid', $userId)
            ->whereBetween('created_at', [$dari, $sampai])
            ->whereNull('deleted_at')
            ->count();

        return $table->render('livewire.order-history', [
            'totalOrder' => $totalOrder,
            'totalIncome' => $totalIncome,
        ]);
    }

    public function orderHistoryDrinks(OrdersDataTable $table)
    {
        [$dari, $sampai] = $this->rentangHariIni();

        $baseQuery = OrderItem::query()
            ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
            ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('order_items.created_at', [$dari, $sampai])
            ->where('products.type', 'drink')
            ->whereNull('orders.deleted_at');

        $totalOrder = (clone $baseQuery)->count();
        $totalIncome = (clone $baseQuery)->sum('order_items.price');

        $drinkAndTotal = (clone $baseQuery)
            ->select('products.name', DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('products.name')
            ->pluck('total_quantity', 'name')
            ->toArray();

        $orderItems = (clone $baseQuery)
            ->select('order_items.*')
            ->with('product')
            ->get();

        return $table->render('livewire.order-history-drinks', [
            'totalOrder' => $totalOrder,
            'totalIncome' => $totalIncome,
            'orderItems' => $orderItems,
            'drinkAndTotal' => $drinkAndTotal,
        ]);
    }

    /**
     * whereDate() membungkus kolom dalam DATE(), yang mematikan pemakaian index
     * dan memaksa konversi timezone per baris. whereBetween tetap sargable.
     */
    private function rentangHariIni(): array
    {
        return [today()->startOfDay(), today()->endOfDay()];
    }
}
