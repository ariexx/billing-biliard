@extends('layouts.app')
@section('content')
{{-- make detail order page with bootstrap --}}
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Order Detail</h3>
                </div>
                <div class="card-body">
                    @foreach ($errors->all() as $error)
                    <div class="alert alert-danger">{{ $error }}</div>
                    @endforeach
                    @if(session()->has('success') || session()->has('status'))
                    <div class="alert alert-success">
                        {{session()->get('success') ?? session()->get('status')}}
                    </div>
                    @endif
                    @if(session()->has('error'))
                    <div class="alert alert-danger">
                        {{session()->get('error')}}
                    </div>
                    @endif
                    <div class="col-lg-6 mb-3">
                        <a class="btn btn-success" href="{{route('order-item.edit', $order->uuid)}}"><i
                                class="fa fa-plus"></i> Tambah Item</a>
                        <a class="btn btn-warning" href="{{route('order.pindah-meja', $order->uuid)}}"><i
                                class="fa fa-info"></i> Pindah Meja</a>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">Order Number : {{$order->order_number}}</li>
                        <li class="list-group-item">Cashier : {{$order->user?->name ?? '-'}}</li>
                        <li class="list-group-item">
                            Status :
                            @if($order->is_paid)
                                <span class="badge bg-success">LUNAS</span>
                                {{ $order->payment?->name }} &middot; {{ $order->paid_at->format('d/m/Y H:i') }}
                            @else
                                <span class="badge bg-warning text-dark">BELUM DIBAYAR</span>
                            @endif
                        </li>
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
                                <!-- <th>Duration</th> -->
                                <th>Sub Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Sama seperti struk: harga dibaca dari order_items,
                                 bukan dari products yang harganya bisa berubah. --}}
                            @foreach($order->orderItems as $item)
                            <tr>
                                <td>{{$item->product?->name ?? 'Produk dihapus'}}</td>
                                <td>{{rupiah((int) round($item->price / max($item->quantity, 1)))}}</td>
                                <td>{{$item->quantity}}</td>
                                <td>{{$item->hour ?? '-'}}</td>
                                <td>{{$item->activeOrder->started_at ?? '-'}}</td>
                                <td>{{$item->activeOrder->end_at ?? '-'}}</td>
                                <td>{{rupiah($item->price)}}</td>
                                <td>
                                    @unless($order->is_paid)
                                    <form action="{{route('order-item.destroy', $item->uuid)}}" method="POST"
                                          onsubmit="return confirm('Hapus item {{ $item->product?->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                    @endunless
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p>
                        <strong>Total: {{rupiah($order->total)}}</strong>
                    </p>

                    @unless($order->is_paid)
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Pembayaran</h5>
                                @if($order->currentSession())
                                    <p class="text-muted mb-0">
                                        Masih ada sesi meja yang berjalan. Selesaikan sesinya dulu di dashboard
                                        sebelum menandai order ini lunas.
                                    </p>
                                @else
                                    <form action="{{route('order.bayar', $order->uuid)}}" method="POST"
                                          class="row g-2 align-items-end"
                                          onsubmit="return confirm('Tandai order ini LUNAS sebesar {{ rupiah($order->total) }}?')">
                                        @csrf
                                        <div class="col-md-5">
                                            <label class="form-label">Metode Pembayaran</label>
                                            <select class="form-select" name="payment_uuid" required>
                                                <option value="">Pilih metode</option>
                                                @foreach(\App\Models\Payment::where('is_active', true)->get() as $metode)
                                                    <option value="{{$metode->uuid}}">{{$metode->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fa fa-check"></i> Tandai Lunas
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endunless
                    @if($order->created_at->diffInDays(now()) < 1 || auth()->user()->role === "admin")
                        <form action="{{route('print')}}" method="POST" target="_blank">
                            @csrf
                            <input type="hidden" name="order_uuid" value="{{$order->uuid}}">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-print"></i> Print
                            </button>
                        </form>
                        @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
