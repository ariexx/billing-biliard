@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-6 mb-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-center"><b>Total Orderan Hari Ini</b></h5>
                        <h1 class="card-text text-center">{{ $totalOrder }}</h1>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-center"><b>Total Pendapatan Hari Ini</b></h5>
                        <h1 class="card-text text-center">{{rupiah($totalIncome)}}</h1>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Order History</h3>
                    </div>
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
