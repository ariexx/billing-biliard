<?php

namespace App\Http\Controllers;

use App\DataTables\OrdersDataTable;
use App\Exports\RiwayatMinumanExport;
use App\Exports\RiwayatOrderExport;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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

    /**
     * Rentang tanggal dari query string, bawaan hari ini.
     *
     * whereDate() membungkus kolom dalam DATE() yang mematikan pemakaian index,
     * jadi seluruh laporan memakai batas awal/akhir hari lewat whereBetween.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rentang(Request $request): array
    {
        $dari = rescue(
            fn () => Carbon::parse($request->query('dari'))->startOfDay(),
            fn () => today()->startOfDay(),
            false
        );

        $sampai = rescue(
            fn () => Carbon::parse($request->query('sampai'))->endOfDay(),
            fn () => today()->endOfDay(),
            false
        );

        // Rentang terbalik hampir pasti salah ketik; ditukar daripada menghasilkan
        // laporan kosong tanpa penjelasan.
        return $dari->gt($sampai) ? [$sampai->copy()->startOfDay(), $dari->copy()->endOfDay()] : [$dari, $sampai];
    }

    /**
     * Kasir hanya melihat ordernya sendiri; admin melihat semuanya.
     */
    private function batasiPerPeran($query, string $kolomUser)
    {
        return $query->when(
            auth()->user()?->role !== 'admin',
            fn ($q) => $q->where($kolomUser, auth()->id())
        );
    }

    public function orderHistory(OrdersDataTable $table, Request $request)
    {
        [$dari, $sampai] = static::rentang($request);

        $itemBiliar = $this->batasiPerPeran(
            OrderItem::query()
                ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
                ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
                ->whereBetween('order_items.created_at', [$dari, $sampai])
                ->where('products.type', 'billiard')
                ->whereNull('orders.deleted_at')
                ->whereNull('order_items.deleted_at'),
            'orders.user_uuid'
        );

        $order = $this->batasiPerPeran(
            DB::table('orders')
                ->whereBetween('created_at', [$dari, $sampai])
                ->whereNull('deleted_at'),
            'user_uuid'
        );

        $jumlahOrder = (clone $order)->count();
        $totalIncome = (int) $itemBiliar->sum('order_items.price');
        $belumLunas = (clone $order)->whereNull('paid_at')->count();

        return $table->render('livewire.order-history', [
            'dari' => $dari,
            'sampai' => $sampai,
            'totalOrder' => $jumlahOrder,
            'totalIncome' => $totalIncome,
            'belumLunas' => $belumLunas,
            'rataOrder' => $jumlahOrder > 0 ? (int) round($totalIncome / $jumlahOrder) : 0,
        ]);
    }

    public function exportOrderHistory(Request $request)
    {
        [$dari, $sampai] = static::rentang($request);

        return Excel::download(
            new RiwayatOrderExport($dari, $sampai, auth()->user()),
            'riwayat-order-'.$dari->format('Ymd').'-'.$sampai->format('Ymd').'.xlsx'
        );
    }

    /**
     * Query dasar penjualan minuman dan snack pada rentang tertentu.
     */
    public function queryMinuman(Carbon $dari, Carbon $sampai)
    {
        return $this->batasiPerPeran(
            OrderItem::query()
                ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
                ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
                ->whereBetween('order_items.created_at', [$dari, $sampai])
                // 'other' ikut: snack dan barang lain juga dijual di kasir yang sama.
                ->whereIn('products.type', ['drink', 'snack', 'other'])
                ->whereNull('orders.deleted_at')
                ->whereNull('order_items.deleted_at'),
            'orders.user_uuid'
        );
    }

    public function orderHistoryDrinks(Request $request)
    {
        [$dari, $sampai] = static::rentang($request);

        $dasar = fn () => $this->queryMinuman($dari, $sampai);

        $totalItem = (int) $dasar()->sum('order_items.quantity');
        $totalIncome = (int) $dasar()->sum('order_items.price');

        $perProduk = $dasar()
            ->selectRaw('products.name as nama, products.type as tipe,
                         SUM(order_items.quantity) as qty, SUM(order_items.price) as total')
            ->groupBy('products.name', 'products.type')
            ->orderByDesc('qty')
            ->get();

        // Dipaginasi: rentang sebulan pada kios yang ramai bisa ribuan baris, dan
        // versi lama menarik semuanya ke memori sekaligus.
        $orderItems = $dasar()
            ->select('order_items.*')
            ->with('product', 'order.user', 'order.payment')
            ->orderByDesc('order_items.created_at')
            ->paginate(25)
            ->withQueryString();

        return view('livewire.order-history-drinks', [
            'dari' => $dari,
            'sampai' => $sampai,
            'totalItem' => $totalItem,
            'totalIncome' => $totalIncome,
            'perProduk' => $perProduk,
            'orderItems' => $orderItems,
        ]);
    }

    public function exportOrderHistoryDrinks(Request $request)
    {
        [$dari, $sampai] = static::rentang($request);

        return Excel::download(
            new RiwayatMinumanExport($dari, $sampai, auth()->user()),
            'riwayat-minuman-'.$dari->format('Ymd').'-'.$sampai->format('Ymd').'.xlsx'
        );
    }
}
