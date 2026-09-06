<?php

namespace App\Filament\Widgets;

use App\Models\ActiveOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\DB;

class RingkasanHariIni extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static string $view = 'filament::widgets.stats-overview-widget';

    protected function getCards(): array
    {
        [$dari, $sampai] = [today()->startOfDay(), today()->endOfDay()];

        $omzet = OrderItem::query()
            ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('order_items.created_at', [$dari, $sampai])
            ->whereNull('orders.deleted_at')
            ->sum('order_items.price');

        $jumlahOrder = Order::whereBetween('created_at', [$dari, $sampai])->count();

        $mejaTerpakai = ActiveOrder::where('is_active', true)->distinct()->count('product_uuid');
        $totalMeja = Product::where('type', 'billiard')->count();

        return [
            Card::make('Omzet Hari Ini', rupiah((int) $omzet))
                ->description('Semua item, termasuk minuman')
                ->descriptionIcon('heroicon-s-cash')
                ->color('success'),

            Card::make('Order Hari Ini', $jumlahOrder)
                ->description('Sejak '.$dari->format('d/m/Y'))
                ->descriptionIcon('heroicon-s-clipboard-list'),

            Card::make('Meja Terpakai', $mejaTerpakai.' / '.$totalMeja)
                ->description($totalMeja - $mejaTerpakai.' meja kosong')
                ->descriptionIcon('heroicon-s-play')
                ->color($mejaTerpakai >= $totalMeja && $totalMeja > 0 ? 'danger' : 'primary'),
        ];
    }
}
