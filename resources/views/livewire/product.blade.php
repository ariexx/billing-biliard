<div class="row">
    @foreach ($billiardProducts as $product)
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><b>{{ $product->name }}</b></h5>
                    <select class="form-select mb-3" name="selectedHour" wire:model="selectedHour">
                        <option selected>Pilih Menu</option>
                        @foreach ($product->hours()->orderBy('hour', 'asc')->get() as $hour)
                            <option value="{{ $hour->uuid }}">{{ $hour->name }}</option>
                        @endforeach
                    </select>
                    <button wire:click.prevent="saveOrder('{{ $product->uuid }}', '{{ $hourUuid }}')"
                        class="btn btn-primary">Order
                    </button>
                </div>
            </div>
        </div>
    @endforeach
</div>
