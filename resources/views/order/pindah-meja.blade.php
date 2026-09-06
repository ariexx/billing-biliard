@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><b>Pindah Meja</b> &mdash; {{ $order->order_number }}</div>
                <div class="card-body">
                    <form action="{{route('order.pindah-meja', $order->uuid)}}" method="POST" onsubmit="return confirm('Yakin pindah meja?')">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="table" class="form-label">Pindahkan ke meja:</label>
                            <select class="form-select" id="table" name="table_uuid" required
                                    @disabled($tables->isEmpty())>
                                <option value="">Pilih meja tujuan</option>
                                @foreach($tables as $table)
                                    <option value="{{$table->uuid}}">{{$table->name}}</option>
                                @endforeach
                            </select>
                            @if($tables->isEmpty())
                                <div class="form-text text-danger">
                                    Semua meja sedang terpakai, tidak ada tujuan yang tersedia.
                                </div>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" @disabled($tables->isEmpty())>Pindah Meja</button>
                        <a href="{{ route('order.view', $order->uuid) }}" class="btn btn-link mt-3">Batal</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
