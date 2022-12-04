@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Tambah Item Order</h3>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">Order Number : {{$order->order_number}}</li>
                        <li class="list-group-item">Cashier : {{$order->user->name}}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
