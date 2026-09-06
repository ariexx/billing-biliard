@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-3"><b>Riwayat Minuman &amp; Snack Hari Ini</b></h3>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title text-muted">Item Terjual Hari Ini</h6>
                    <h1 class="card-text mb-0">{{ $totalOrder }}</h1>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title text-muted">Pendapatan Minuman Hari Ini</h6>
                    <h1 class="card-text mb-0 text-success">{{ rupiah($totalIncome) }}</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card h-100">
                <div class="card-header">Rincian Penjualan</div>
                <div class="card-body">
                    @if ($orderItems->isEmpty())
                        <p class="text-muted mb-0">Belum ada penjualan minuman hari ini.</p>
                    @else
                        {{-- Tabel ini sebelumnya tanpa class Bootstrap sama sekali, jadi
                             tampil sebagai tabel HTML polos tanpa border maupun padding. --}}
                        <div class="table-responsive">
                            <table id="order-history-drinks" class="table table-striped table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>No. Order</th>
                                        <th>Kasir</th>
                                        <th>Nama Barang</th>
                                        <th>Jumlah</th>
                                        <th>Total</th>
                                        <th>Metode Bayar</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Semua relasi null-safe: user yang di-soft-delete atau
                                         payment yang terhapus sebelumnya membuat halaman 500. --}}
                                    @foreach ($orderItems as $orderItem)
                                        <tr>
                                            <td>{{ $orderItem->order?->order_number ?? '-' }}</td>
                                            <td>{{ $orderItem->order?->user?->name ?? '-' }}</td>
                                            <td>{{ $orderItem->product?->name ?? 'Produk dihapus' }}</td>
                                            <td>{{ $orderItem->quantity }}</td>
                                            <td>{{ rupiah($orderItem->price) }}</td>
                                            <td>{{ $orderItem->order?->payment?->name ?? '-' }}</td>
                                            <td>{{ $orderItem->created_at?->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-header">Terjual per Produk</div>
                <div class="card-body">
                    @if (empty($drinkAndTotal))
                        <p class="text-muted mb-0">Belum ada data.</p>
                    @else
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th class="text-end">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($drinkAndTotal as $nama => $jumlah)
                                    <tr>
                                        <td>{{ $nama }}</td>
                                        <td class="text-end"><b>{{ $jumlah }}</b></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="//cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="//cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
<script>
    $(document).ready(function () {
        // Hanya diinisialisasi kalau tabelnya benar-benar dirender; saat tidak ada
        // penjualan, elemennya tidak ada dan DataTables melempar error di console.
        if ($('#order-history-drinks').length) {
            $('#order-history-drinks').DataTable({
                order: [[6, 'desc']],
                language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', zeroRecords: 'Tidak ada data' }
            });
        }
    });
</script>
@endpush
