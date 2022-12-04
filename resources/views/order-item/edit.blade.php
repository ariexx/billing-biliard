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
                        <form action="{{route('order-item.update', $order->uuid)}}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="form-group mb-3 col-md-6">
                                <label for="exampleFormControlSelect1">Tambah Additional</label>
                                <select class="form-control" name="product">
                                    <option value="">Pilih Menu</option>
                                    @foreach($products as $product)
                                        <option value="{{$product->uuid}}">{{$product->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="exampleFormControlSelect1">Tambah Waktu</label>
                                <select class="form-control" name="hour">
                                    <option value="">Pilih waktu</option>
                                    @foreach($hours as $hour)
                                        <option value="{{$hour->uuid}}">{{$hour->hour}} Jam</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">Tambah</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
