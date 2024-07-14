@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Pindah Meja Billiard</div>
                <div class="card-body">
                    <form action="{{route('order.pindah-meja', $order->uuid)}}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="table">Select New Table:</label>
                            <select class="form-select" id="table" name="table_uuid">
                                <option value="">Select Table</option>
                                @foreach($tables as $table)
                                    <option value="{{$table->uuid}}">{{$table->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" onclick="confirm('Yakin ?')">Pindah Meja</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
