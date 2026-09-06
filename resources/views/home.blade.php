@extends('layouts.app')

@section('content')
    <div class="container">
        {{-- Bilah atas: pencarian cepat dan jumlah order yang belum dibayar.
             Keduanya hal yang dibutuhkan kasir kapan saja, jadi tidak boleh
             ikut tergulir bersama grid meja. --}}
        <div class="row g-2 align-items-center mb-4">
            <div class="col-md-7">
                <form action="{{ route('order.cari') }}" method="GET" class="d-flex gap-2">
                    <input type="search" name="q" class="form-control"
                           placeholder="Cari nomor order atau nama meja..."
                           value="{{ request('q') }}" autocomplete="off">
                    <button class="btn btn-outline-primary flex-shrink-0">
                        <i class="fa fa-search"></i> Cari
                    </button>
                </form>
            </div>
            <div class="col-md-5 text-md-end">
                @if ($belumDibayar->isNotEmpty())
                    <a href="#belum-dibayar" class="btn btn-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        {{ $belumDibayar->count() }} order belum dibayar
                    </a>
                @else
                    <span class="text-muted small">Semua order sudah dibayar</span>
                @endif
            </div>
        </div>

        <livewire:meja-grid />

        @if ($belumDibayar->isNotEmpty())
            <div class="card border-warning mt-4" id="belum-dibayar">
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
