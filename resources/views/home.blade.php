@extends('layouts.app')

@section('content')
    <div class="container">
        <livewire:product />

        <hr class="my-4"/>

        <livewire:active-order/>

        @if ($belumDibayar->isNotEmpty())
            <hr class="my-4"/>

            <div class="card border-warning mb-4">
                <div class="card-header bg-warning bg-opacity-25 d-flex justify-content-between align-items-center">
                    <b>Belum Dibayar</b>
                    <span class="badge bg-warning text-dark">{{ $belumDibayar->count() }}</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Sesi mejanya sudah selesai tapi pembayarannya belum ditandai lunas.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No. Order</th>
                                    <th>Meja</th>
                                    <th>Waktu</th>
                                    <th class="text-end">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($belumDibayar as $order)
                                    <tr>
                                        <td>{{ $order->order_number }}</td>
                                        <td>
                                            {{ $order->orderItems
                                                ->first(fn ($item) => $item->product?->type === 'Billiard')
                                                ?->product?->name ?? '-' }}
                                        </td>
                                        <td>{{ $order->created_at?->format('d/m H:i') }}</td>
                                        <td class="text-end"><b>{{ rupiah($order->total) }}</b></td>
                                        <td class="text-end">
                                            <a href="{{ route('order.view', $order->uuid) }}"
                                               class="btn btn-sm btn-warning">Bayar</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
