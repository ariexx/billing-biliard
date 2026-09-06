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
            {{-- order_items.price adalah total baris yang dikunci saat transaksi.
                 Jangan pernah membacanya dari products.price: harga produk bisa
                 berubah, dan struk lama yang dicetak ulang jadi tidak menjumlah. --}}
            @foreach($order->orderItems as $item)
                <tr>
                    <td>
                        {{ $item->product?->name ?? 'Produk dihapus' }}
                        @if($item->hour || $item->durasiMenit() !== null) - {{ $item->labelDurasi() }} @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ rupiah((int) round($item->price / max($item->quantity, 1))) }}</td>
                    <td>{{ rupiah($item->price) }}</td>
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
