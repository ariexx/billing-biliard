@extends('layouts.app')

@section('content')
<div class="container">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0"><b>Riwayat Order</b></h3>
            <small class="text-muted">
                {{ $dari->translatedFormat('d M Y') }}
                @if ($dari->toDateString() !== $sampai->toDateString())
                    &ndash; {{ $sampai->translatedFormat('d M Y') }}
                @endif
                @if (auth()->user()->role !== 'admin')
                    &middot; order Anda saja
                @endif
            </small>
        </div>

        <a href="{{ route('order-history.export', request()->only('dari', 'sampai')) }}"
           class="btn btn-outline-success">
            <i class="fa fa-file-excel"></i> Export Excel
        </a>
    </div>

    {{-- Filter ini mengatur kartu KPI DAN tabel di bawahnya. URL-nya ikut dipakai
         sebagai alamat ajax tabel, jadi keduanya selalu satu periode. --}}
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
                    <a href="{{ route('order-history') }}" class="btn btn-outline-secondary">Hari Ini</a>
                    <a href="{{ route('order-history', ['dari' => today()->subDays(6)->toDateString(), 'sampai' => today()->toDateString()]) }}"
                       class="btn btn-outline-secondary">7 Hari</a>
                    <a href="{{ route('order-history', ['dari' => today()->startOfMonth()->toDateString(), 'sampai' => today()->toDateString()]) }}"
                       class="btn btn-outline-secondary">Bulan Ini</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Jumlah Order</div>
                    <div class="fs-3 fw-bold">{{ $totalOrder }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Pendapatan Biliar</div>
                    <div class="fs-4 fw-bold text-success">{{ rupiah($totalIncome) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">Rata-rata per Order</div>
                    <div class="fs-4 fw-bold">{{ rupiah($rataOrder) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 {{ $belumLunas > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <div class="small text-muted">Belum Dibayar</div>
                    <div class="fs-3 fw-bold {{ $belumLunas > 0 ? 'text-warning' : '' }}">{{ $belumLunas }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                {{ $dataTable->table(['class' => 'table table-striped align-middle w-100']) }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
