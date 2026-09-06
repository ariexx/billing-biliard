<?php

namespace App\Filament\Pages;

use App\Models\ActiveOrder;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan rentang tanggal.
 *
 * Widget dashboard terkunci di "hari ini" dan "7 hari", jadi sebelum ini
 * pertanyaan "bulan lalu dapat berapa" hanya bisa dijawab dengan mengexport data
 * mentah lalu mengolahnya di Excel.
 */
class Laporan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected static string $view = 'filament.pages.laporan';

    public ?string $dari = null;

    public ?string $sampai = null;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->form->fill([
            'dari' => today()->startOfMonth()->toDateString(),
            'sampai' => today()->toDateString(),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('dari')->label('Dari tanggal')->required()->reactive(),
                Forms\Components\DatePicker::make('sampai')->label('Sampai tanggal')->required()->reactive(),
            ]),
        ];
    }

    private function rentang(): array
    {
        return [
            Carbon::parse($this->dari ?: today()->startOfMonth())->startOfDay(),
            Carbon::parse($this->sampai ?: today())->endOfDay(),
        ];
    }

    /**
     * Basis semua angka: item order dalam rentang, tanpa order yang dihapus.
     * SoftDeletes hanya membatasi tabel modelnya sendiri, jadi orders.deleted_at
     * harus difilter manual pada join mentah seperti ini.
     */
    private function itemQuery()
    {
        [$dari, $sampai] = $this->rentang();

        return OrderItem::query()
            ->join('orders', 'order_items.order_uuid', '=', 'orders.uuid')
            ->join('products', 'order_items.product_uuid', '=', 'products.uuid')
            ->whereBetween('order_items.created_at', [$dari, $sampai])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at');
    }

    public function getRingkasanProperty(): array
    {
        [$dari, $sampai] = $this->rentang();

        $omzet = (int) (clone $this->itemQuery())->sum('order_items.price');
        $biliar = (int) (clone $this->itemQuery())->where('products.type', 'billiard')->sum('order_items.price');
        $lain = $omzet - $biliar;

        $jumlahOrder = Order::whereBetween('created_at', [$dari, $sampai])->count();
        $belumLunas = Order::whereBetween('created_at', [$dari, $sampai])->whereNull('paid_at')->count();

        return [
            'omzet' => $omzet,
            'biliar' => $biliar,
            'lain' => $lain,
            'jumlah_order' => $jumlahOrder,
            'belum_lunas' => $belumLunas,
            'rata_order' => $jumlahOrder > 0 ? (int) round($omzet / $jumlahOrder) : 0,
        ];
    }

    public function getPerHariProperty()
    {
        return (clone $this->itemQuery())
            ->selectRaw('DATE(order_items.created_at) as tanggal, SUM(order_items.price) as total')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();
    }

    public function getPerMetodeBayarProperty()
    {
        [$dari, $sampai] = $this->rentang();

        // Hanya order yang sudah ditandai lunas: metode bayar order yang belum
        // dibayar belum tentu final.
        return Order::query()
            ->join('payments', 'orders.payment_uuid', '=', 'payments.uuid')
            ->join('order_items', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('orders.created_at', [$dari, $sampai])
            ->whereNotNull('orders.paid_at')
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            // Alias 'total' TIDAK boleh dipakai di sini: query ini mengembalikan model
            // Order, dan Order::getTotalAttribute() akan menutupi kolom hasil select.
            ->selectRaw('payments.name as metode, SUM(order_items.price) as total_omzet, COUNT(DISTINCT orders.uuid) as jumlah')
            ->groupBy('payments.name')
            ->orderByDesc('total_omzet')
            ->get();
    }

    public function getPerKasirProperty()
    {
        [$dari, $sampai] = $this->rentang();

        return Order::query()
            ->join('users', 'orders.user_uuid', '=', 'users.uuid')
            ->join('order_items', 'order_items.order_uuid', '=', 'orders.uuid')
            ->whereBetween('orders.created_at', [$dari, $sampai])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->selectRaw('users.name as kasir, SUM(order_items.price) as total_omzet, COUNT(DISTINCT orders.uuid) as jumlah')
            ->groupBy('users.name')
            ->orderByDesc('total_omzet')
            ->get();
    }

    public function getProdukTerlarisProperty()
    {
        return (clone $this->itemQuery())
            ->where('products.type', '!=', 'billiard')
            ->selectRaw('products.name as produk, SUM(order_items.quantity) as qty, SUM(order_items.price) as total')
            ->groupBy('products.name')
            ->orderByDesc('qty')
            ->limit(15)
            ->get();
    }

    /**
     * Pemanfaatan meja: pendapatan, jumlah sesi, dan total menit terpakai.
     *
     * Menit dihitung dari active_orders karena di situlah waktu main tercatat.
     * Sesi yang masih berjalan dihitung sampai sekarang.
     */
    public function getPemanfaatanMejaProperty()
    {
        [$dari, $sampai] = $this->rentang();

        $pendapatan = (clone $this->itemQuery())
            ->where('products.type', 'billiard')
            ->selectRaw('products.uuid as meja_uuid, SUM(order_items.price) as total')
            ->groupBy('products.uuid')
            ->pluck('total', 'meja_uuid');

        // Menit dihitung di PHP, bukan lewat TIMESTAMPDIFF/LEAST: fungsi itu
        // khusus MySQL dan tidak ada di sqlite yang dipakai test. Jumlah barisnya
        // kecil (sesi dalam satu rentang tanggal), jadi tidak jadi masalah.
        $sesi = ActiveOrder::with('product')
            ->whereBetween('started_at', [$dari, $sampai])
            ->get();

        $jamOperasionalPerHari = 14;
        $hari = max(1, $dari->diffInDays($sampai) + 1);
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

    public function getJamTersibukProperty()
    {
        [$dari, $sampai] = $this->rentang();

        // HOUR() juga khusus MySQL; dikelompokkan di PHP agar portabel.
        return ActiveOrder::whereBetween('started_at', [$dari, $sampai])
            ->get()
            ->groupBy(fn ($s) => (int) $s->started_at->format('G'))
            ->map(fn ($baris, $jam) => (object) ['jam' => $jam, 'jumlah' => $baris->count()])
            ->sortKeys()
            ->values();
    }
}
