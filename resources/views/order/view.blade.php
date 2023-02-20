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
                        @if(session()->has('success'))
                            <div class="alert alert-success">
                                {{session()->get('success')}}
                            </div>
                        @elseif(session()->has('error'))
                            <div class="alert alert-danger">
                                {{session()->get('error')}}
                            </div>
                        @endif
                        <div class="col-lg-6 mb-3">
                            <a class="btn btn-success" href="{{route('order-item.edit', $order->uuid)}}"><i
                                    class="fa fa-plus"></i> Tambah Item</a>
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
                                <th>Hour</th>
                                <th>Started At</th>
                                <th>Ended At</th>
                                <th>Duration</th>
                                <th>Sub Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($order->orderItems as $item)
                                <tr>
                                    <td>{{$item->product->name}}</td>
                                    <td>
                                        @if($item->product->type === "Billiard")
                                            {{rupiah($item->price)}}
                                        @else
                                            {{rupiah($item->product->price)}}
                                        @endif
                                    </td>
                                    <td>{{$item->quantity}}</td>
                                    <td>{{$item->activeOrder->hour ?? '-'}}</td>
                                    <td>{{$item->activeOrder->started_at ?? '-'}}</td>
                                    <td>{{$item->activeOrder->end_at ?? '-'}}</td>
                                    <td>{{$item->activeOrder->duration ?? '-'}} Menit</td>
                                    <td>
                                        @if($item->product->type === "Billiard")
                                            {{rupiah($item->quantity * $item->price)}}
                                        @else
                                            {{rupiah($item->quantity * $item->product->price)}}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <p>
                            <strong>Total: {{rupiah($order->total)}}</strong>
                        </p>
                        @if($order->created_at->diffInDays(now()) < 1 || auth()->user()->role === "admin")
                            <form action="{{route('print')}}" method="POST">
                                @csrf
                                <input type="hidden" name="order_uuid" value="{{$order->uuid}}">
                                <button type="submit" class="btn btn-primary"><i
                                        class="fa fa-print"></i>
                                    Print
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
