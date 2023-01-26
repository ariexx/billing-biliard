@extends('layouts.app')
@push('css')
<!-- <link rel="stylesheet" href="//cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css"> -->
@endpush
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-6 mb-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-center"><b>Total Orderan Minuman Hari ini</b></h5>
                    <h1 class="card-text text-center">{{ $totalOrder }}</h1>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-center"><b>Total Pendapatan Minuman Hari Ini</b></h5>
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
                    <table id="order-history-drinks">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Cashier</th>
                                <th>Item Name</th>
                                <th>Total</th>
                                <th>Payment Method</th>
                                <th>Dibuat Pada</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orderItems as $orderItem)
                            <tr>
                                <td>{{ $orderItem->order->order_number }}</td>
                                <td>{{ $orderItem->order->user->name }}</td>
                                <td>{{ $orderItem->product->name }}</td>
                                <td>{{ rupiah($orderItem->price) }}</td>
                                <td>{{ $orderItem->order->payment->name }}</td>
                                <td>{{ $orderItem->created_at}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="//cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function () {
        $('#order-history-drinks').DataTable();
    });
</script>
@endpush
