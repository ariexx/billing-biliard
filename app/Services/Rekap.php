<?php

namespace App\Services;

use App\Models\ActiveOrder;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;

/**
 * Sumber tunggal seluruh angka laporan.
 *
 * Dipakai bersama oleh halaman Laporan di panel admin dan rekap harian Telegram.
 * Kalau logikanya digandakan, cepat atau lambat kedua tempat itu menampilkan
 * "omzet" yang berbeda untuk hari yang sama -- dan pada sistem uang itu fatal.
 */
class Rekap
{
    private Carbon $dari;

    private Carbon $sampai;

    public function __construct($dari, $sampai)
    {
        $this->dari = Carbon::parse($dari)->startOfDay();
        $this->sampai = Carbon::parse($sampai)->endOfDay();
    }

    public static function harian($tanggal): static
    {
        return new static($tanggal, $tanggal);
    }

    public function dari(): Carbon
    {
        return $this->dari->copy();
    }

    public function sampai(): Carbon
    {
        return $this->sampai->copy();
    }

    /**
     * Basis semua angka: item order dalam rentang, tanpa order yang dihapus.
     * SoftDeletes hanya membatasi tabel modelnya sendiri, jadi orders.deleted_at
     * harus difilter manual pada join mentah seperti ini.
     */
    private function itemQuery()
    {
        return OrderItem::query()
            ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
            ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
            ->whereBetween('order_items.created_at', [$this->dari, $this->sampai])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at');
    }

    public function ringkasan(): array
    {
        $omzet = (int) $this->itemQuery()->sum('order_items.price');
        $biliar = (int) $this->itemQuery()->where('products.type', 'billiard')->sum('order_items.price');

        $jumlahOrder = Order::whereBetween('created_at', [$this->dari, $this->sampai])->count();
        $belumLunas = Order::whereBetween('created_at', [$this->dari, $this->sampai])->whereNull('paid_at')->count();

        return [
            'omzet' => $omzet,
            'biliar' => $biliar,
            'lain' => $omzet - $biliar,
            'jumlah_order' => $jumlahOrder,
            'belum_lunas' => $belumLunas,
            'rata_order' => $jumlahOrder > 0 ? (int) round($omzet / $jumlahOrder) : 0,
        ];
    }

    public function perHari()
    {
        return $this->itemQuery()
            ->selectRaw('DATE(order_items.created_at) as tanggal, SUM(order_items.price) as total')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();
    }

    /**
     * Hanya order yang sudah ditandai lunas: metode bayar order yang belum
     * dibayar belum tentu final.
     *
     * Alias SUM sengaja BUKAN 'total': query ini mengembalikan model Order, dan
     * Order::getTotalAttribute() akan menutupinya sehingga hasilnya terbaca 0.
     */
    public function perMetodeBayar()
    {
        return Order::query()
            ->join('payments', 'orders.payment_uuid', '=', 'payments.uuid')
            ->join('order_items', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('orders.created_at', [$this->dari, $this->sampai])
            ->whereNotNull('orders.paid_at')
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->selectRaw('payments.name as metode, SUM(order_items.price) as total_omzet, COUNT(DISTINCT orders.uuid) as jumlah')
            ->groupBy('payments.name')
            ->orderByDesc('total_omzet')
            ->get();
    }

    public function perKasir()
    {
        return Order::query()
            ->join('users', 'orders.user_uuid', '=', 'users.uuid')
            ->join('order_items', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('orders.created_at', [$this->dari, $this->sampai])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->selectRaw('users.name as kasir, SUM(order_items.price) as total_omzet, COUNT(DISTINCT orders.uuid) as jumlah')
            ->groupBy('users.name')
            ->orderByDesc('total_omzet')
            ->get();
    }

    public function produkTerlaris(int $batas = 15)
    {
        return $this->itemQuery()
            ->where('products.type', '!=', 'billiard')
            ->selectRaw('products.name as produk, SUM(order_items.quantity) as qty, SUM(order_items.price) as total')
            ->groupBy('products.name')
            ->orderByDesc('qty')
            ->limit($batas)
            ->get();
    }

    /**
     * Pemanfaatan meja: pendapatan, jumlah sesi, dan total menit terpakai.
     *
     * Menit dihitung di PHP, bukan lewat TIMESTAMPDIFF/LEAST: fungsi itu khusus
     * MySQL dan tidak ada di sqlite yang dipakai test. Jumlah barisnya kecil.
     */
    public function pemanfaatanMeja(int $jamOperasionalPerHari = 14)
    {
        $pendapatan = $this->itemQuery()
            ->where('products.type', 'billiard')
            ->selectRaw('products.uuid as meja_uuid, SUM(order_items.price) as total')
            ->groupBy('products.uuid')
            ->pluck('total', 'meja_uuid');

        $sesi = ActiveOrder::with('product')
            ->whereBetween('started_at', [$this->dari, $this->sampai])
            ->get();

        $hari = max(1, $this->dari->diffInDays($this->sampai) + 1);
        $menitTersedia = $hari * $jamOperasionalPerHari * 60;

        return $sesi->groupBy('product_uuid')->map(function ($baris, $mejaUuid) use ($pendapatan, $menitTersedia) {
            $menit = $baris->sum(function ($s) {
                // Sesi yang masih berjalan dihitung sampai sekarang.
                $selesai = $s->end_at && $s->end_at->isPast() ? $s->end_at : now();

                return max(0, $s->started_at->diffInMinutes($selesai));
            });

            return [
                'meja' => $baris->first()->product?->name ?? 'Meja dihapus',
                'jumlah_sesi' => $baris->count(),
                'menit' => (int) $menit,
                'pendapatan' => (int) ($pendapatan[$mejaUuid] ?? 0),
                'pemakaian' => $menitTersedia > 0
                    ? min(100, round($menit / $menitTersedia * 100, 1))
                    : 0,
            ];
        })->sortByDesc('pendapatan')->values();
    }

    public function jamTersibuk()
    {
        // HOUR() juga khusus MySQL; dikelompokkan di PHP agar portabel.
        return ActiveOrder::whereBetween('started_at', [$this->dari, $this->sampai])
            ->get()
            ->groupBy(fn ($s) => (int) $s->started_at->format('G'))
            ->map(fn ($baris, $jam) => (object) ['jam' => $jam, 'jumlah' => $baris->count()])
            ->sortKeys()
            ->values();
    }
}
