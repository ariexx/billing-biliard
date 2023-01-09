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
		return $table->render('livewire.order-history', [
			'totalOrder' => $totalOrder,
			'totalIncome' => $totalIncome
		]);
	}
}
