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
		$totalIncome = OrderItem::query()?->whereDate('created_at', today())?->sum('price');

        //get total income this month
        $totalIncomeMonth = OrderItem::query()?->whereMonth('created_at', now()?->month)?->sum('price');

        $totalDrinkIncomeToday = OrderItem::query()?->whereDate('created_at', today())
            ?->whereHas('product', function ($query) {
                $query?->where('type', 'drink');
            })?->sum('price');

        $totalDrinkIncomeMonth = OrderItem::query()?->whereMonth('created_at', now()?->month)
            ?->whereHas('product', function ($query) {
                $query?->where('type', 'drink');
            })?->sum('price');

		return $table->render('livewire.order-history', [
			'totalOrder' => $totalOrder,
			'totalIncome' => $totalIncome,
            'totalDrinkIncomeToday' => $totalDrinkIncomeToday,
            'totalDrinkIncomeMonth' => $totalDrinkIncomeMonth,
		]);
	}
}
