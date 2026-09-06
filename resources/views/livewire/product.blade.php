<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><b>Pilih Meja</b></h3>
        <span class="text-muted small">
            {{ $billiardProducts->count() - $sesiPerMeja->count() }} dari {{ $billiardProducts->count() }} meja kosong
        </span>
    </div>

    @if ($billiardProducts->isEmpty())
        <div class="alert alert-warning">
            Belum ada meja biliar. Tambahkan produk bertipe <b>billiard</b> di panel admin.
        </div>
    @endif

    <div class="row">
        @foreach ($billiardProducts as $product)
            @php $sesi = $sesiPerMeja->get($product->uuid); @endphp

            <div class="col-md-4 col-lg-3 mb-3">
                <div class="card h-100 {{ $sesi ? 'border-danger' : 'border-success' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><b>{{ $product->name }}</b></h5>
                            @if ($sesi)
                                <span class="badge bg-danger">Terpakai</span>
                            @else
                                <span class="badge bg-success">Kosong</span>
                            @endif
                        </div>

                        @if ($sesi)
                            {{-- Meja terpakai: tombol Order disembunyikan supaya kasir tidak
                                 mengklik sesuatu yang pasti ditolak server. --}}
                            <p class="small text-muted mb-2">
                                Mulai {{ $sesi->started_at?->format('H:i') }}
                                &middot; {{ ucwords($sesi->hour_type) }}
                            </p>
                            <a href="{{ route('order.view', $sesi->order_uuid) }}"
                               class="btn btn-outline-secondary btn-sm mt-auto" target="_blank">
                                Lihat Order
                            </a>
                        @else
                            @if ($product->hours->isEmpty())
                                <p class="small text-danger mb-0">
                                    Belum ada paket jam untuk meja ini. Atur di panel admin.
                                </p>
                            @else
                                <label class="form-label small text-muted mb-1">Durasi</label>
                                <select class="form-select form-select-sm mb-3"
                                        wire:model="selectedHours.{{ $product->uuid }}">
                                    <option value="">Pilih durasi</option>
                                    @foreach ($product->hours as $hour)
                                        <option value="{{ $hour->uuid }}">
                                            {{ $hour->name }} &mdash;
                                            {{ $hour->type === 'free time'
                                                ? rupiah($hour->price).'/menit'
                                                : rupiah($hour->price) }}
                                        </option>
                                    @endforeach
                                </select>

                                <button wire:click.prevent="saveOrder('{{ $product->uuid }}')"
                                        class="btn btn-primary w-100 mt-auto"
                                        wire:loading.attr="disabled"
                                        wire:target="saveOrder('{{ $product->uuid }}')">
                                    <span wire:loading.remove wire:target="saveOrder('{{ $product->uuid }}')">
                                        <i class="fa fa-play"></i> Mulai
                                    </span>
                                    <span wire:loading wire:target="saveOrder('{{ $product->uuid }}')">
                                        <span class="spinner-border spinner-border-sm"></span> Memproses...
                                    </span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
