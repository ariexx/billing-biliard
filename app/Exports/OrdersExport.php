<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * maatwebsite/excel sudah terpasang sejak awal tapi tidak pernah dipakai;
 * ini pemakaian pertamanya.
 */
class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $orders)
    {
    }

    public function collection(): Collection
    {
        return $this->orders->load('user', 'payment', 'orderItems.product');
    }

    public function headings(): array
    {
        return ['No. Order', 'Tanggal', 'Kasir', 'Meja', 'Metode Bayar', 'Jumlah Item', 'Total'];
    }

    public function map($order): array
    {
        $meja = $order->orderItems
            ->first(fn ($item) => $item->product?->type === 'Billiard')?->product?->name;

        return [
            $order->order_number,
            $order->created_at?->format('d/m/Y H:i'),
            $order->user?->name ?? '-',
            $meja ?? '-',
            $order->payment?->name ?? '-',
            $order->orderItems->count(),
            (int) $order->orderItems->sum('price'),
        ];
    }
}
