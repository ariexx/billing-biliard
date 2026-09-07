<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RiwayatMinumanExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private Carbon $dari,
        private Carbon $sampai,
        private ?User $pengguna
    ) {
    }

    public function collection(): Collection
    {
        // Memakai query yang sama persis dengan halamannya, supaya angka di layar
        // dan di berkas export tidak pernah berbeda.
        return app(\App\Http\Controllers\HomeController::class)
            ->queryMinuman($this->dari, $this->sampai)
            ->select('order_items.*')
            ->with('product', 'order.user', 'order.payment')
            ->orderByDesc('order_items.created_at')
            ->get();
    }

    public function headings(): array
    {
        return ['No. Order', 'Tanggal', 'Kasir', 'Produk', 'Tipe', 'Jumlah', 'Total', 'Metode Bayar'];
    }

    public function map($item): array
    {
        return [
            $item->order?->order_number ?? '-',
            $item->created_at?->format('d/m/Y H:i'),
            $item->order?->user?->name ?? '-',
            $item->product?->name ?? 'Produk dihapus',
            $item->product?->type ?? '-',
            $item->quantity,
            (int) $item->price,
            $item->order?->payment?->name ?? '-',
        ];
    }
}
