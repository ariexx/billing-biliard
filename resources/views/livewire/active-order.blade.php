<div wire:poll.10000ms>
    <div class="row">
        <h3><b>Meja Aktif</b></h3>
        @foreach ($activeOrder as $order)
            @if (!$order->end_at->isPast() && $order->hour < 100)
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><b>{{ $order->product->name }}</b></h5>
                            <h6 class="card-subtitle mb-2 text-muted">Habis dalam</h6>
                            <b>
                                <x-countdown :expires="$order->end_at">
                                    <span x-text="timer.hours">{{ $component->hours() }}</span> hours
                                    <span x-text="timer.minutes">{{ $component->minutes() }}</span> minutes
                                    <span x-text="timer.seconds">{{ $component->seconds() }}</span> seconds
                                </x-countdown>
                            </b>
                        </div>
                    </div>
                </div>
            @elseif($order->hour > 100)
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><b>{{ $order->product->name }}</b></h5>
                            <h6 class="card-subtitle mb-2 text-muted">Main bebas</h6>
                            <b>
                                <x-countdown :expires="$order->end_at">
                                    <span x-text="timer.hours">{{ $component->hours() }}</span> hours
                                    <span x-text="timer.minutes">{{ $component->minutes() }}</span> minutes
                                    <span x-text="timer.seconds">{{ $component->seconds() }}</span> seconds
                                </x-countdown>
                            </b>
                            <button class="btn btn-danger btn-sm mt-2"
                                wire:click="stopTimer('{{ $order->order_uuid }}')">Selesai</button>
                        </div>
                    </div>
                </div>
            @else
                @php
                    \App\Models\ActiveOrder::whereOrderUuid($order->order_uuid)->update(['is_active' => false]);
                @endphp
            @endif
        @endforeach
    </div>
</div>
