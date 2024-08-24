<div wire:poll.10000ms>
    <div class="row">
        <h3><b>Meja Aktif</b></h3>
        @foreach ($activeOrder as $order)
            @if ($order->is_active && !$order->end_at->isPast() && $order->hour_type == "regular")
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
                            <p class="mb-2">
                                <a href="{{ route('order.view', $order->order_uuid) }}" class="text-sm-left text-muted" style="text-decoration: none;" target="_blank">
                                    Lihat Detail Order
                                </a>
                            </p>
                            <button class="btn btn-danger btn-sm mt-2" wire:click.prevent="habiskanWaktu('{{ $order->unique_id }}')">
                                Habiskan
                            </button>
                        </div>
                    </div>
                </div>
            @elseif ($order->is_active && $order->hour_type == "free time")
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
                            <p class="mb-2">
                                <a href="{{ route('order.view', $order->order_uuid) }}" class="text-sm-left text-muted" style="text-decoration: none;" target="_blank">
                                    Lihat Detail Order
                                </a>
                            </p>
                            <button class="btn btn-danger btn-sm mt-2" wire:click.prevent="stopTimer('{{ $order->unique_id }}')">
                                Selesai
                            </button>
                        </div>
                    </div>
                </div>
            @else
                @php
                    $order->update(['is_active' => false]);
                @endphp
            @endif
        @endforeach
    </div>
</div>
