{{-- Satu kartu per meja fisik, posisinya tidak pernah berpindah (urut nama),
     supaya kasir menemukan meja dari letaknya tanpa perlu membaca.
     Poll 10 detik ini juga yang memperbarui sisa waktu dan tagihan berjalan. --}}
<div wire:poll.10000ms>
    @php
        $kosong = $meja->reject(fn ($m) => $sesiPerMeja->has($m->uuid))->count();
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><b>Meja</b></h4>
        <span class="text-muted small">{{ $kosong }} dari {{ $meja->count() }} kosong</span>
    </div>

    @if ($meja->isEmpty())
        <div class="alert alert-warning">
            Belum ada meja biliar. Tambahkan produk bertipe <b>billiard</b> di panel admin.
        </div>
    @endif

    <div class="row g-3">
        @foreach ($meja as $m)
            @php
                $sesi = $sesiPerMeja->get($m->uuid);
                $bebas = $sesi && $sesi->hour_type === 'free time';

                // Dibulatkan ke ATAS: diffInMinutes memotong ke bawah, sehingga
                // blok satu jam langsung tampil "0j 59m" sedetik setelah dimulai.
                $sisaMenit = $sesi && ! $bebas
                    ? (int) ceil(max(0, (int) now()->diffInSeconds($sesi->end_at, false)) / 60)
                    : null;

                $status = match (true) {
                    ! $sesi => 'kosong',
                    $bebas => 'bebas',
                    $sisaMenit <= 0 => 'habis',
                    $sisaMenit <= \App\Http\Livewire\MejaGrid::AMBANG_PERINGATAN => 'segera',
                    default => 'jalan',
                };

                // Warna SELALU dipasangkan teks status: monitor kios sering murah
                // dan sebagian orang sulit membedakan merah-hijau.
                [$garis, $lencana, $labelStatus] = match ($status) {
                    'kosong' => ['border-success', 'bg-success', 'Kosong'],
                    'jalan'  => ['border-primary', 'bg-primary', 'Jalan'],
                    'segera' => ['border-warning', 'bg-warning text-dark', 'Segera habis'],
                    'habis'  => ['border-danger',  'bg-danger',  'Waktu habis'],
                    'bebas'  => ['border-info',    'bg-info text-dark', 'Main bebas'],
                };
            @endphp

            <div class="col-6 col-md-4 col-xl-3">
                <div class="card h-100 border-2 {{ $garis }}" data-status="{{ $status }}"
                     data-meja="{{ $m->name }}">
                    <div class="card-body d-flex flex-column p-3">

                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><b>{{ $m->name }}</b></h5>
                            <span class="badge {{ $lencana }}">{{ $labelStatus }}</span>
                        </div>

                        @if (! $sesi)
                            @if ($m->hours->isEmpty())
                                <p class="small text-danger mb-0">
                                    Belum ada paket jam. Atur di panel admin.
                                </p>
                            @else
                                <select class="form-select mb-2" wire:model="selectedHours.{{ $m->uuid }}">
                                    <option value="">Pilih durasi</option>
                                    @foreach ($m->hours as $hour)
                                        <option value="{{ $hour->uuid }}">
                                            {{ $hour->name }} &mdash;
                                            {{ $hour->type === 'free time'
                                                ? rupiah($hour->price).'/mnt'
                                                : rupiah($hour->price) }}
                                        </option>
                                    @endforeach
                                </select>

                                <button wire:click.prevent="saveOrder('{{ $m->uuid }}')"
                                        class="btn btn-success w-100 mt-auto py-2"
                                        wire:loading.attr="disabled"
                                        wire:target="saveOrder('{{ $m->uuid }}')">
                                    <span wire:loading.remove wire:target="saveOrder('{{ $m->uuid }}')">
                                        <i class="fa fa-play"></i> Mulai
                                    </span>
                                    <span wire:loading wire:target="saveOrder('{{ $m->uuid }}')">
                                        <span class="spinner-border spinner-border-sm"></span>
                                    </span>
                                </button>
                            @endif
                        @else
                            {{-- Angka waktu sengaja jadi elemen terbesar di kartu:
                                 itu satu-satunya yang perlu terbaca dari jauh. --}}
                            @if ($bebas)
                                <div class="mb-1">
                                    <div class="fs-3 lh-1">
                                        <b>{{ intdiv($sesi->menit_berjalan, 60) }}j {{ $sesi->menit_berjalan % 60 }}m</b>
                                    </div>
                                    <div class="small text-muted">sudah main</div>
                                </div>
                                <div class="mb-2">
                                    <b class="text-success">{{ rupiah($sesi->tagihan_berjalan) }}</b>
                                    <span class="small text-muted">tagihan</span>
                                </div>
                            @else
                                <div class="mb-2">
                                    <div class="fs-3 lh-1">
                                        <b>{{ intdiv(max(0, $sisaMenit), 60) }}j {{ max(0, $sisaMenit) % 60 }}m</b>
                                    </div>
                                    <div class="small text-muted">
                                        sisa &middot; sampai {{ $sesi->end_at?->format('H:i') }}
                                    </div>
                                </div>
                            @endif

                            <div class="mt-auto d-grid gap-2">
                                <a href="{{ route('order.view', $sesi->order_uuid) }}"
                                   class="btn btn-outline-secondary btn-sm" target="_blank">Detail &amp; Bayar</a>

                                @if ($bebas)
                                    {{-- stopImmediatePropagation menahan listener Livewire pada
                                         elemen yang sama; handler atribut inline selalu terdaftar
                                         lebih dulu karena dipasang saat HTML di-parse. --}}
                                    <button class="btn btn-danger btn-sm"
                                            wire:loading.attr="disabled"
                                            wire:target="stopTimer('{{ $sesi->order_uuid }}', '{{ $sesi->unique_id }}')"
                                            onclick="if (!confirm('Selesaikan main bebas {{ $m->name }}? Tagihan dikunci sebesar {{ rupiah($sesi->tagihan_berjalan) }}.')) { event.stopImmediatePropagation(); event.preventDefault(); }"
                                            wire:click.prevent="stopTimer('{{ $sesi->order_uuid }}', '{{ $sesi->unique_id }}')">
                                        Selesai
                                    </button>
                                @else
                                    <button class="btn btn-danger btn-sm"
                                            onclick="if (!confirm('Akhiri sesi {{ $m->name }}? Blok jam yang sudah dibayar tidak dikembalikan.')) { event.stopImmediatePropagation(); event.preventDefault(); }"
                                            wire:click.prevent="habiskanWaktu('{{ $sesi->unique_id }}')">
                                        Habiskan
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
