@extends('layouts.app')

@section('content')
<div class="container">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0"><b>Riwayat Minuman &amp; Snack</b></h3>
            <small class="text-muted">
                {{ $dari->translatedFormat('d M Y') }}
                @if ($dari->toDateString() !== $sampai->toDateString())
                    &ndash; {{ $sampai->translatedFormat('d M Y') }}
                @endif
                @if (auth()->user()->role !== 'admin')
                    &middot; penjualan Anda saja
                @endif
            </small>
        </div>

        <a href="{{ route('order-history.drinks.export', request()->only('dari', 'sampai')) }}"
           class="btn btn-outline-success">
            <i class="fa fa-file-excel"></i> Export Excel
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Dari tanggal</label>
                    <input type="date" name="dari" class="form-control" value="{{ $dari->toDateString() }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Sampai tanggal</label>
                    <input type="date" name="sampai" class="form-control" value="{{ $sampai->toDateString() }}">
                </div>
                <div class="col-12 col-md-6 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary"><i class="fa fa-filter"></i> Terapkan</button>
                    <a href="{{ route('order-history.drinks') }}" class="btn btn-outline-secondary">Hari Ini</a>
                    <a href="{{ route('order-history.drinks', ['dari' => today()->subDays(6)->toDateString(), 'sampai' => today()->toDateString()]) }}"
                       class="btn btn-outline-secondary">7 Hari</a>
                    <a href="{{ route('order-history.drinks', ['dari' => today()->startOfMonth()->toDateString(), 'sampai' => today()->toDateString()]) }}"
                       class="btn btn-outline-secondary">Bulan Ini</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Item Terjual</div>
                    <div class="fs-3 fw-bold">{{ number_format($totalItem, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Pendapatan</div>
                    <div class="fs-4 fw-bold text-success">{{ rupiah($totalIncome) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <b>Rincian Penjualan</b>
                    <span class="text-muted small">{{ $orderItems->total() }} baris</span>
                </div>
                <div class="card-body">
                    @if ($orderItems->isEmpty())
                        <p class="text-muted mb-0">Tidak ada penjualan pada periode ini.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>No. Order</th>
                                        <th>Produk</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Total</th>
                                        <th>Kasir</th>
                                        <th>Bayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Semua relasi null-safe: kasir yang di-soft-delete
                                         atau produk yang dihapus sebelumnya membuat
                                         halaman ini 500. --}}
                                    @foreach ($orderItems as $item)
                                        <tr>
                                            <td class="text-nowrap">{{ $item->created_at?->format('d/m H:i') }}</td>
                                            <td>
                                                @if ($item->order)
                                                    <a href="{{ route('order.view', $item->order->uuid) }}"
                                                       class="text-decoration-none">{{ $item->order->order_number }}</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $item->product?->name ?? 'Produk dihapus' }}</td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end fw-semibold">{{ rupiah($item->price) }}</td>
                                            <td>{{ $item->order?->user?->name ?? '-' }}</td>
                                            <td>{{ $item->order?->payment?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $orderItems->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><b>Terjual per Produk</b></div>
                <div class="card-body">
                    @if ($perProduk->isEmpty())
                        <p class="text-muted mb-0">Belum ada data.</p>
                    @else
                        @php $maks = max(1, (int) $perProduk->max('qty')); @endphp
                        <div class="d-flex flex-column gap-2">
                            @foreach ($perProduk as $p)
                                <div>
                                    <div class="d-flex justify-content-between small">
                                        <span>{{ $p->nama }}</span>
                                        <span class="text-muted">
                                            <b>{{ $p->qty }}</b> &middot; {{ rupiah((int) $p->total) }}
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-success"
                                             style="width: {{ round($p->qty / $maks * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
