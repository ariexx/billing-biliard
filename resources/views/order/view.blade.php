@extends('layouts.app')
@section('content')
    {{--    make detail order page with bootstrap --}}
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Order Detail</h3>
                    </div>
                    <div class="card-body">
                        <div class="col-lg-6 mb-3">
                            <a class="btn btn-success" href="{{route('order-item.edit', $order->uuid)}}">Tambah Item</a>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">Order Number : {{$order->order_number}}</li>
                            <li class="list-group-item">Cashier : {{$order->user->name}}</li>
                        </ul>
                        <table class="table table-bordered text-center">
                            <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Product Price</th>
                                <th>Product Quantity</th>
                                <th>Sub Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($order->orderItems as $item)
                                <tr>
                                    <td>{{$item->product->name}}</td>
                                    <td>{{$item->price}}</td>
                                    <td>{{$item->quantity}}</td>
                                    <td>{{$item->quantity * $item->price}}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <p>
                            <strong>Total: {{rupiah($order->total)}}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection