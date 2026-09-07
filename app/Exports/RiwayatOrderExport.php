<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export riwayat order untuk kasir.
 *
 * Pembatasan peran ikut diterapkan di sini, bukan hanya di tampilan: tanpa itu
 * kasir bisa mengunduh seluruh order milik kasir lain lewat URL export.
 */
class RiwayatOrderExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private Carbon $dari,
        private Carbon $sampai,
        private ?User $pengguna
    ) {
    }

    public function collection(): Collection
    {
        return Order::query()
            ->with('user', 'payment', 'orderItems.product')
            ->whereBetween('created_at', [$this->dari, $this->sampai])
            ->when(
                $this->pengguna?->role !== 'admin',
                fn ($q) => $q->where('user_uuid', $this->pengguna?->uuid)
            )
            ->orderByDesc('created_at')
            ->get();
    }

    public function headings(): array
    {
        return ['No. Order', 'Tanggal', 'Kasir', 'Meja', 'Metode Bayar', 'Status', 'Jumlah Item', 'Total'];
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
            $order->is_paid ? 'Lunas' : 'Belum dibayar',
            $order->orderItems->count(),
            (int) $order->orderItems->sum('price'),
        ];
    }
}
