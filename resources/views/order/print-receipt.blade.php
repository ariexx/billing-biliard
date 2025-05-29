@extends('layouts.print')

@section('content')
    <div class="receipt">
        <div class="header">
            <h2 class="store-name">Black Dragon Pool</h2>
            <p class="store-address">Jalan Kapten Pattimura No.93, Lubuk Pakam</p>
        </div>

        <div class="order-info">
            <p><strong>Order #:</strong> {{ $order->order_number }}</p>
            <p><strong>Date:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
            <p><strong>Cashier:</strong> {{ $order->user->name }}</p>
        </div>

        <table class="items">
            <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($order->orderItems as $item)
                <tr>
                    <td>
                        {{ $item->product->name }}
                        @if($item->hour) - {{ $item->hour }} Jam @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>
                        @if($item->product->type === "Billiard")
                            {{ rupiah($item->price) }}
                        @else
                            {{ rupiah($item->product->price) }}
                        @endif
                    </td>
                    <td>
                        @if($item->product->type === "Billiard")
                            {{ rupiah($item->quantity * $item->price) }}
                        @else
                            {{ rupiah($item->quantity * $item->product->price) }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <th colspan="3">Total</th>
                <th>{{ rupiah($order->total) }}</th>
            </tr>
            </tfoot>
        </table>

        <div class="footer">
            <p>Terimakasih datang kembali</p>
        </div>
    </div>
@endsection
