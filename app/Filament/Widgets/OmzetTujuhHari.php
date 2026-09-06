<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\DB;

class OmzetTujuhHari extends LineChartWidget
{
    protected static ?int $sort = 2;

    protected function getHeading(): string
    {
        return 'Omzet 7 Hari Terakhir';
    }

    protected function getData(): array
    {
        $mulai = today()->subDays(6)->startOfDay();

        // Dikelompokkan di SQL supaya tidak menarik seluruh order item ke PHP.
        $perHari = OrderItem::query()
            ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('order_items.created_at', [$mulai, today()->endOfDay()])
            ->whereNull('orders.deleted_at')
            ->selectRaw('DATE(order_items.created_at) as tanggal, SUM(order_items.price) as total')
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        $label = [];
        $nilai = [];

        foreach (range(6, 0) as $mundur) {
            $hari = today()->subDays($mundur);
            $label[] = $hari->format('d/m');
            $nilai[] = (int) ($perHari[$hari->format('Y-m-d')] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Omzet (Rp)',
                'data' => $nilai,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                'fill' => true,
            ]],
            'labels' => $label,
        ];
    }
}
