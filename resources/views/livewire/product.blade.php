<div class="row">
    @foreach($billiardProducts as $product)
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{$product->name}}</h5>
                    <select class="form-select mb-3" aria-label="Default select example">
                        <option selected>Pilih waktu</option>
                        @foreach($product->hours as $hour)
                            <option value="{{$hour->uuid}}">{{$hour->hour}}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary ">Submit</button>
                </div>
            </div>
        </div>
    @endforeach
</div>
