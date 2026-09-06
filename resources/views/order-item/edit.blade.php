@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Tambah Item &mdash; {{ $order->order_number }}</span>
                    <a href="{{ route('order.view', $order->uuid) }}" class="btn btn-sm btn-outline-secondary">
                        Kembali ke Order
                    </a>
                </div>
                <div class="card-body">
                    {{-- Dua blok terpisah: dengan @elseif, pesan "Waktu belum habis"
                         hilang diam-diam setiap kali ada error validasi juga. --}}
                    @foreach ($errors->all() as $error)
                        <div class="alert alert-danger">{{ $error }}</div>
                    @endforeach
                    @if (session()->has('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form id="form-tambah-item" action="{{ route('order-item.update', $order->uuid) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <h6 class="text-muted">Menu</h6>
                        {{-- Baris digandakan dari <template>, bukan dirakit dari string
                             HTML. Versi lama menyalin id="quantity" dan id="remove_btn"
                             ke setiap baris (HTML tidak valid) dan menghapus baris dengan
                             .parent().prev().remove() dua kali -- rusak begitu markup
                             berubah sedikit saja. --}}
                        <div id="daftar-item">
                            <div class="row g-2 align-items-end mb-2 baris-item">
                                <div class="col-md-6">
                                    <label class="form-label">Produk</label>
                                    <select class="form-select" name="product[]">
                                        <option value="">Pilih menu</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->uuid }}">
                                                {{ $product->name }} &mdash; {{ rupiah($product->price) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Jumlah</label>
                                    <input type="number" min="1" step="1" class="form-control"
                                           name="quantity[]" placeholder="Jumlah">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-danger w-100 hapus-baris" disabled>
                                        <i class="fa fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" id="tambah-baris" class="btn btn-outline-primary btn-sm mb-4">
                            <i class="fa fa-plus"></i> Tambah Baris
                        </button>

                        <h6 class="text-muted">Perpanjang Waktu <small>(opsional)</small></h6>
                        <div class="row g-2 mb-4">
                            <div class="col-md-6">
                                <select class="form-select" name="hour">
                                    <option value="">Tidak menambah waktu</option>
                                    @foreach($hours as $hour)
                                        <option value="{{ $hour->uuid }}">
                                            {{ $hour->name }} &mdash;
                                            {{ $hour->type === 'free time'
                                                ? rupiah($hour->price).'/menit (main bebas)'
                                                : rupiah($hour->price) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="template-baris">
    <div class="row g-2 align-items-end mb-2 baris-item">
        <div class="col-md-6">
            <label class="form-label">Produk</label>
            <select class="form-select" name="product[]">
                <option value="">Pilih menu</option>
                @foreach($products as $product)
                    <option value="{{ $product->uuid }}">{{ $product->name }} &mdash; {{ rupiah($product->price) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Jumlah</label>
            <input type="number" min="1" step="1" class="form-control" name="quantity[]" placeholder="Jumlah">
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-outline-danger w-100 hapus-baris">
                <i class="fa fa-trash"></i> Hapus
            </button>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script type="text/javascript">
    (function () {
        const daftar = document.getElementById('daftar-item');
        const template = document.getElementById('template-baris');

        document.getElementById('tambah-baris').addEventListener('click', function () {
            daftar.appendChild(template.content.cloneNode(true));
        });

        // Delegasi: tombol hapus pada baris yang baru ditambahkan ikut tertangani.
        daftar.addEventListener('click', function (event) {
            const tombol = event.target.closest('.hapus-baris');

            if (!tombol || tombol.disabled) {
                return;
            }

            tombol.closest('.baris-item').remove();
        });

        // Cegah kirim ganda: submit kedua membuat item order dobel.
        // Dicari lewat id, bukan querySelector('form') -- form pertama di halaman
        // ini adalah form logout milik navbar.
        const form = document.getElementById('form-tambah-item');

        form.addEventListener('submit', function (event) {
            if (form.dataset.terkirim === '1') {
                event.preventDefault();
                return;
            }

            form.dataset.terkirim = '1';

            // Ditunda satu tick: menonaktifkan tombol secara sinkron di dalam
            // handler submit bisa membatalkan pengiriman di sebagian browser.
            setTimeout(function () {
                const tombol = form.querySelector('button[type="submit"]');
                tombol.disabled = true;
                tombol.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';
            }, 0);
        });
    })();
</script>
@endpush
