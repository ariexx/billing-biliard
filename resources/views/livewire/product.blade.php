<div class="row">
    @foreach ($billiardProducts as $product)
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><b>{{ $product->name }}</b></h5>
                    {{-- $product->hours sudah di-eager-load terurut oleh komponen;
                         jangan panggil ->hours()->get() di sini (N+1 tiap render). --}}
                    <select class="form-select mb-3" name="selectedHour_{{ $product->uuid }}" wire:model="selectedHours.{{ $product->uuid }}">
                        <option value="">Pilih Menu</option>
                        @foreach ($product->hours as $hour)
                            <option value="{{ $hour->uuid }}">{{ $hour->name }}</option>
                        @endforeach
                    </select>
                    <button wire:click.prevent="saveOrder('{{ $product->uuid }}')"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                            wire:target="saveOrder('{{ $product->uuid }}')">
                        <span wire:loading.remove wire:target="saveOrder('{{ $product->uuid }}')">Order</span>
                        <span wire:loading wire:target="saveOrder('{{ $product->uuid }}')">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endforeach
</div>
