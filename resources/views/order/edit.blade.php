
@extends('layouts.app')

@section('content')
{{-- make a form edit for selected product with bootstrap --}}

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Edit Order</h3>
                </div>
                <div class="card-body">
                    <form action="{{route('order.update', $order->uuid)}}" method="post">
                        @csrf
                        @method('PUT')
                        @foreach($order->orderItems as $item)
                            <div class="form-group">
                                <label for="name">Name</label>
                                <select class="form-select">
                                    <option value="{{$item->product->uuid}}">{{$item->product->name}}</option>
                                </select>
                            </div>
                        @endforeach

@endsection
