<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><b>Product Billiard</b></h5>
                <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-order" role="tabpanel"
                         aria-labelledby="pills-order-tab">
                        <label for="name">Nomor Meja</label>
                        <select class="form-select mb-3" name="selectedProduct" wire:model="selectedProduct">
                            <option selected>Pilih Meja</option>
                            @foreach($billiardProducts as $billiardProduct)
                                <option value="{{ $billiardProduct['uuid'] }}">{{ $billiardProduct['name'] }}</option>
                            @endforeach
                        </select>

                        @if($productHours)
                            <label for="name">Waktu</label>
                            <select class="form-select mb-3" name="selectedHour" wire:model="selectedHour">
                                <option selected>Pilih Waktu</option>
                                @foreach($productHours as $productHour)
                                    <option value="{{ $productHour->uuid }}">{{ $productHour->hour }}</option>
                                @endforeach
                            </select>
                        @endif
                        <button wire:click.prevent="saveOrder('{{ $selectedProduct }}', '{{ $selectedHour }}')"
                                class="btn btn-primary">Pesan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><b>Produk Minuman</b></h5>
                <label for="pills-drink">Pilih Menu</label>
                <select class="form-select mb-3" name="selectedDrink" wire:model="selectedDrink">
                    <option selected>Pilih Minuman</option>
                    @foreach($drinkProducts as $drinkProduct)
                        <option value="{{ $drinkProduct['uuid'] }}">{{ $drinkProduct['name'] }}</option>
                    @endforeach
                </select>
                <button wire:click.prevent="saveDrink('{{ $selectedDrink }}')"
                        class="btn btn-primary">Pesan
                </button>
            </div>
        </div>
    </div>
</div>
