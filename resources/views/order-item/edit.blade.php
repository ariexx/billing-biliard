@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <h5 class="card-header">Order Item</h5>
                <div class="card-body">
                    @if (session()->has('errors'))
                    @foreach ($errors->all() as $error)
                    <div class="alert alert-danger">
                        {{ $error }}
                    </div>
                    @endforeach
                    @endif
                    <form class="row g-3" action="{{ route('order-item.update', $order->uuid) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="col-md-6">
                            <label for="product" class="form-label">Menu</label>
                            <select class="form-control" name="product[]">
                                <option value="">Pilih Menu</option>
                                @foreach($products as $product)
                                <option value="{{$product->uuid}}">{{$product->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="quantity" class="form-label">Jumlah</label>
                            <input type="number" class="form-control" id="quantity" name="quantity[]"
                                placeholder="Jumlah">
                        </div>
                        <div class="col-md-4">
                            <label for="add_btn">Tambah Input</label>
                            <input type="button" class="form-control btn btn-primary" id="add_btn" value="Add Input">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Hour</label>
                            <select class="form-control" name="hour">
                                <option value="">Pilih waktu</option>
                                @foreach($hours as $hour)
                                <option value="{{$hour->uuid}}">{{$hour->name}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script type="text/javascript">
    $(document).ready(function () {
        $('#add_btn').on('click', function () {
            let html = '';
            html += '<div class="col-md-6">';
            html += '<label for="product" class="form-label">Menu</label>';
            html += '<select class="form-control" name="product[]">';
            html += '<option value="">Pilih Menu</option>';
            @foreach($products as $product)
            html += '<option value="{{$product->uuid}}">{{$product->name}}</option>';
            @endforeach
            html += '</select>';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<label for="quantity" class="form-label">Jumlah</label>';
            html += '<input type="text" class="form-control" id="quantity" name="quantity[]" placeholder="Jumlah">';
            html += '</div>';
            html += '<div class="col-md-4">';
            html += '<label for="remove_btn">Hapus Input</label>';
            html += '<input type="button" class="form-control btn btn-danger" id="remove_btn" value="Remove Input">';
            html += '</div>';
            $('#add_btn').parent().after(html);
        });
    });

    $(document).on('click', '#remove_btn', function () {
        $(this).parent().prev().remove();
        $(this).parent().prev().remove();
        $(this).parent().remove();
    });
</script>
@endpush
