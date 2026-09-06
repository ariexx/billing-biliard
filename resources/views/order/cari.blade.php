@extends('layouts.app')

@section('content')
<div class="container">
    <h4 class="mb-3">Hasil pencarian &ldquo;{{ $q }}&rdquo;</h4>

    @if ($hasil->isEmpty())
        <div class="alert alert-warning">
            Tidak ada order yang cocok. Coba nomor order (mis. <code>ORD-20260906-0002</code>)
            atau nama meja.
        </div>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">Kembali</a>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Meja</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hasil as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>
                                {{ $order->orderItems
                                    ->first(fn ($i) => $i->product?->type === 'Billiard')
                                    ?->product?->name ?? '-' }}
                            </td>
                            <td>{{ $order->created_at?->format('d/m H:i') }}</td>
                            <td>
                                @if ($order->is_paid)
                                    <span class="badge bg-success">Lunas</span>
                                @else
                                    <span class="badge bg-warning text-dark">Belum dibayar</span>
                                @endif
                            </td>
                            <td class="text-end"><b>{{ rupiah($order->total) }}</b></td>
                            <td class="text-end">
                                <a href="{{ route('order.view', $order->uuid) }}"
                                   class="btn btn-sm btn-primary">Buka</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
