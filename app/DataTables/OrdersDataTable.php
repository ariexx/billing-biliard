<?php

namespace App\DataTables;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class OrdersDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     * @return \Yajra\DataTables\EloquentDataTable
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($row) {
                return '<a href="' . route('order.view', $row->uuid) . '" class="btn btn-sm btn-primary">View</a>';
            })
            ->editColumn('created_at', function (Order $order) {
                return $order->created_at->format('d/m/Y H:i:s');
            })
            ->addColumn('cashier', function (Order $order) {
                return $order->user?->name ?? '-';
            })
            ->addColumn('total', function (Order $order) {
                return 'Rp ' . number_format($order->orderItems->sum('price'), 2, ',', '.');
            })
            ->addColumn('table_number', function (Order $order) {
                // Baris pertama belum tentu mejanya -- bisa sebotol minuman yang
                // ditambahkan lebih dulu. Yang dicari baris bertipe billiard.
                return $order->orderItems
                    ->first(fn ($item) => $item->product?->type === 'Billiard')
                    ?->product?->name ?? '-';
            })
            ->addColumn('payment_method', function (Order $order) {
                return $order->payment?->name ?? '-';
            })
            ->addColumn('status', function (Order $order) {
                return $order->is_paid
                    ? '<span class="badge bg-success">Lunas</span>'
                    : '<span class="badge bg-warning text-dark">Belum dibayar</span>';
            })
            ->rawColumns(['action', 'status'])
            ->setRowId('uuid');
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Order $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    /**
     * Kasir hanya melihat ordernya sendiri. Sebelumnya query ini polos, sehingga
     * kartu "Total Orderan Hari Ini" milik kasir yang login berdiri di atas tabel
     * berisi seluruh order semua kasir sejak aplikasi dipakai.
     */
    public function query(Order $model): QueryBuilder
    {
        $query = $model->newQuery()->with(['user', 'payment', 'orderItems.product']);

        if (auth()->user()?->role !== 'admin') {
            $query->where('user_uuid', auth()->id());
        }

        // Rentang tanggal dibaca dari query string. URL ajax tabel ini memakai
        // url()->full() (lihat html()), jadi parameternya ikut terbawa dan tabel
        // menampilkan periode yang sama dengan kartu KPI di atasnya. Sebelumnya
        // kartu berbunyi "hari ini" sementara tabelnya memuat seluruh riwayat.
        [$dari, $sampai] = \App\Http\Controllers\HomeController::rentang(request());
        $query->whereBetween('created_at', [$dari, $sampai]);

        return $query;
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('orders-table')
            ->columns($this->getColumns())
            // url()->full() membawa parameter dari/sampai ke permintaan ajax.
            ->ajax(url()->full())
            ->orderBy(6, 'desc')
            ->selectStyleSingle()
            // Tombol export TIDAK dipakai di sisi klien: plugin DataTables Buttons
            // belum tentu termuat, dan itulah sebabnya dom('Bfrtip') dulu dimatikan.
            // Export dikerjakan server lewat route order-history.export.
            ->parameters([
                'language' => [
                    'search' => 'Cari:',
                    'lengthMenu' => 'Tampilkan _MENU_ baris',
                    'zeroRecords' => 'Tidak ada order pada periode ini',
                    'info' => 'Menampilkan _START_-_END_ dari _TOTAL_ order',
                    'infoEmpty' => 'Tidak ada order',
                    'paginate' => ['previous' => 'Sebelumnya', 'next' => 'Berikutnya'],
                ],
            ])
            ->responsive(true)
            ->serverSide(true);
    }

    /**
     * Get the dataTable columns definition.
     *
     * @return array
     */
    public function getColumns(): array
    {
        // cashier/table_number/total/payment_method bukan kolom di tabel orders.
        // Dengan serverSide(true), Column::make() membuat Yajra menerjemahkan klik
        // header atau ketikan di kotak search menjadi "order by total" / "where
        // orders.cashier like ?" -> SQLSTATE 42S22 Unknown column.
        return [
            Column::make('order_number')->title('No. Order'),
            Column::computed('cashier')->title('Kasir'),
            Column::computed('table_number')->title('Meja'),
            Column::computed('total')->title('Total'),
            Column::computed('payment_method')->title('Metode Bayar'),
            Column::computed('status')->title('Status'),
            Column::make('created_at')->title('Waktu'),
            Column::computed('action')
                ->title('')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'Orders_' . date('YmdHis');
    }
}
