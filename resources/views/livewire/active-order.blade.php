{{-- Poll 10 detik ini sekaligus yang memperbarui sisa waktu dan tagihan berjalan.
     Sebelumnya kartu memakai <x-countdown> dari blade-ui-kit yang butuh Alpine,
     padahal Alpine tidak pernah dimuat di aplikasi ini -- angkanya tidak pernah
     berdetak. Semua waktu sekarang dirender server, jadi pasti akurat. --}}
<div wire:poll.10000ms>
    <h3 class="mb-3"><b>Meja Aktif</b></h3>

    @php $aktif = $activeOrder->whereIn('hour_type', ['regular', 'free time']); @endphp

    @if ($aktif->isEmpty())
        <div class="alert alert-light border text-muted mb-0">
            Belum ada meja yang sedang dipakai.
        </div>
    @endif

    <div class="row">
        @foreach ($aktif as $order)
            @if ($order->hour_type === 'regular')
                @php
                    // Dibulatkan ke ATAS: diffInMinutes memotong ke bawah, sehingga
                    // blok satu jam langsung tampil "0j 59m" sedetik setelah dimulai.
                    $sisaDetik = max(0, (int) now()->diffInSeconds($order->end_at, false));
                    $sisaMenit = (int) ceil($sisaDetik / 60);
                    $totalMenit = max(1, (int) $order->started_at->diffInMinutes($order->end_at));
                    $persen = max(0, min(100, (int) round($sisaMenit / $totalMenit * 100)));
                    $habis = $sisaMenit <= 0;
                @endphp

                <div class="col-md-4 mb-3">
                    <div class="card h-100 {{ $habis ? 'border-danger' : '' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title mb-1"><b>{{ $order->product?->name ?? 'Meja dihapus' }}</b></h5>
                                <span class="badge {{ $habis ? 'bg-danger' : 'bg-primary' }}">Reguler</span>
                            </div>

                            @if ($habis)
                                <p class="text-danger mb-2"><b>Waktu habis</b></p>
                            @else
                                <p class="mb-1 text-muted small">Sisa waktu</p>
                                {{-- Alpine (v2, dimuat oleh @bukScripts) membuat angka ini
                                     berdetak tiap detik; isi di dalamnya adalah nilai
                                     render server, jadi tetap benar kalau CDN Alpine
                                     tidak bisa dihubungi. --}}
                                <p class="fs-4 mb-2">
                                    <b>
                                        <x-countdown :expires="$order->end_at">
                                            <span x-text="timer.hours">{{ $component->hours() }}</span>j
                                            <span x-text="timer.minutes">{{ $component->minutes() }}</span>m
                                            <span x-text="timer.seconds">{{ $component->seconds() }}</span>d
                                        </x-countdown>
                                    </b>
                                </p>
                                <div class="progress mb-2" style="height: 6px;">
                                    <div class="progress-bar {{ $persen < 20 ? 'bg-danger' : 'bg-success' }}"
                                         role="progressbar" style="width: {{ $persen }}%"
                                         aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            @endif

                            <p class="small text-muted mb-2">
                                {{ $order->started_at?->format('H:i') }} &rarr; {{ $order->end_at?->format('H:i') }}
                            </p>

                            <div class="mt-auto d-flex gap-2">
                                <a href="{{ route('order.view', $order->order_uuid) }}"
                                   class="btn btn-outline-secondary btn-sm" target="_blank">Detail</a>
                                {{-- stopImmediatePropagation menahan listener Livewire pada
                                     elemen yang sama; handler atribut inline selalu terdaftar
                                     lebih dulu karena dipasang saat HTML di-parse. --}}
                                <button class="btn btn-danger btn-sm"
                                        onclick="if (!confirm('Akhiri sesi meja {{ $order->product?->name }}? Blok jam yang sudah dibayar tidak dikembalikan.')) { event.stopImmediatePropagation(); event.preventDefault(); }"
                                        wire:click.prevent="habiskanWaktu('{{ $order->unique_id }}')">
                                    Habiskan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="col-md-4 mb-3">
                    <div class="card h-100 border-info">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title mb-1"><b>{{ $order->product?->name ?? 'Meja dihapus' }}</b></h5>
                                <span class="badge bg-info text-dark">Main Bebas</span>
                            </div>

                            <p class="mb-1 text-muted small">Sudah main</p>
                            <p class="fs-5 mb-1">
                                <b>{{ intdiv($order->menit_berjalan, 60) }}j {{ $order->menit_berjalan % 60 }}m</b>
                            </p>
                            <p class="mb-2">
                                Tagihan sekarang:
                                <b class="text-success">{{ rupiah($order->tagihan_berjalan) }}</b>
                            </p>
                            <p class="small text-muted mb-2">Mulai {{ $order->started_at?->format('H:i') }}</p>

                            <div class="mt-auto d-flex gap-2">
                                <a href="{{ route('order.view', $order->order_uuid) }}"
                                   class="btn btn-outline-secondary btn-sm" target="_blank">Detail</a>
                                <button class="btn btn-danger btn-sm"
                                        wire:loading.attr="disabled"
                                        wire:target="stopTimer('{{ $order->order_uuid }}', '{{ $order->unique_id }}')"
                                        onclick="if (!confirm('Selesaikan sesi main bebas meja {{ $order->product?->name }}? Tagihan dikunci sebesar {{ rupiah($order->tagihan_berjalan) }}.')) { event.stopImmediatePropagation(); event.preventDefault(); }"
                                        wire:click.prevent="stopTimer('{{ $order->order_uuid }}', '{{ $order->unique_id }}')">
                                    Selesai
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
