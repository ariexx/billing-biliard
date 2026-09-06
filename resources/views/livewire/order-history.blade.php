@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-6 mb-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-center text-muted">Total Order Hari Ini</h6>
                        <h1 class="card-text text-center mb-0">{{ $totalOrder }}</h1>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-center text-muted">Pendapatan Biliar Hari Ini</h6>
                        <h1 class="card-text text-center mb-0 text-success">{{rupiah($totalIncome)}}</h1>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header"><b>Riwayat Order</b></div>
                    <div class="card-body">
                        {{ $dataTable->table() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
