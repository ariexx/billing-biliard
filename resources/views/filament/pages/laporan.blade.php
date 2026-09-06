<x-filament::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $r = $this->ringkasan;
        $maksHarian = max(1, (int) $this->perHari->max('total'));
        $maksJam = max(1, (int) $this->jamTersibuk->max('jumlah'));
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-filament::card>
            <div class="text-sm text-gray-500">Omzet</div>
            <div class="text-2xl font-bold">{{ rupiah($r['omzet']) }}</div>
            <div class="text-xs text-gray-500 mt-1">
                Biliar {{ rupiah($r['biliar']) }} &middot; Lainnya {{ rupiah($r['lain']) }}
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Order</div>
            <div class="text-2xl font-bold">{{ $r['jumlah_order'] }}</div>
            <div class="text-xs text-gray-500 mt-1">
                Rata-rata {{ rupiah($r['rata_order']) }} per order
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Belum Dibayar</div>
            <div class="text-2xl font-bold">{{ $r['belum_lunas'] }}</div>
            <div class="text-xs text-gray-500 mt-1">order dalam rentang ini</div>
        </x-filament::card>
    </div>

    <x-filament::card>
        <h3 class="text-lg font-bold mb-3">Omzet per Hari</h3>
        @if ($this->perHari->isEmpty())
            <p class="text-sm text-gray-500">Tidak ada transaksi pada rentang ini.</p>
        @else
            <div class="space-y-1">
                @foreach ($this->perHari as $baris)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-24 shrink-0 text-gray-500">
                            {{ \Illuminate\Support\Carbon::parse($baris->tanggal)->format('d/m/Y') }}
                        </span>
                        <span class="flex-1 bg-gray-100 dark:bg-gray-700 rounded h-4 overflow-hidden">
                            <span class="block bg-primary-500 h-4"
                                  style="width: {{ round($baris->total / $maksHarian * 100) }}%"></span>
                        </span>
                        <span class="w-32 text-right font-medium">{{ rupiah((int) $baris->total) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-filament::card>
            <h3 class="text-lg font-bold mb-3">Per Metode Pembayaran</h3>
            <p class="text-xs text-gray-500 mb-2">Hanya order yang sudah ditandai lunas.</p>
            @if ($this->perMetodeBayar->isEmpty())
                <p class="text-sm text-gray-500">Belum ada order lunas pada rentang ini.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-gray-500 border-b">
                        <tr>
                            <th class="text-left py-1">Metode</th>
                            <th class="text-right">Order</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->perMetodeBayar as $baris)
                            <tr class="border-b last:border-0">
                                <td class="py-1">{{ $baris->metode }}</td>
                                <td class="text-right">{{ $baris->jumlah }}</td>
                                <td class="text-right font-medium">{{ rupiah((int) $baris->total_omzet) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::card>

        <x-filament::card>
            <h3 class="text-lg font-bold mb-3">Per Kasir</h3>
            @if ($this->perKasir->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada data.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-gray-500 border-b">
                        <tr>
                            <th class="text-left py-1">Kasir</th>
                            <th class="text-right">Order</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->perKasir as $baris)
                            <tr class="border-b last:border-0">
                                <td class="py-1">{{ $baris->kasir }}</td>
                                <td class="text-right">{{ $baris->jumlah }}</td>
                                <td class="text-right font-medium">{{ rupiah((int) $baris->total_omzet) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::card>
    </div>

    <x-filament::card>
        <h3 class="text-lg font-bold mb-1">Pemanfaatan Meja</h3>
        <p class="text-xs text-gray-500 mb-3">
            Tingkat pemakaian dihitung terhadap asumsi 14 jam operasional per hari.
        </p>
        @if ($this->pemanfaatanMeja->isEmpty())
            <p class="text-sm text-gray-500">Belum ada sesi meja pada rentang ini.</p>
        @else
            <table class="w-full text-sm">
                <thead class="text-gray-500 border-b">
                    <tr>
                        <th class="text-left py-1">Meja</th>
                        <th class="text-right">Sesi</th>
                        <th class="text-right">Jam Dipakai</th>
                        <th class="text-right">Pemakaian</th>
                        <th class="text-right">Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pemanfaatanMeja as $baris)
                        <tr class="border-b last:border-0">
                            <td class="py-1 font-medium">{{ $baris['meja'] }}</td>
                            <td class="text-right">{{ $baris['jumlah_sesi'] }}</td>
                            <td class="text-right">{{ number_format($baris['menit'] / 60, 1, ',', '.') }} jam</td>
                            <td class="text-right">{{ $baris['pemakaian'] }}%</td>
                            <td class="text-right font-medium">{{ rupiah($baris['pendapatan']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-filament::card>
            <h3 class="text-lg font-bold mb-3">Jam Tersibuk</h3>
            <p class="text-xs text-gray-500 mb-2">Berdasarkan jam meja mulai dipakai.</p>
            @if ($this->jamTersibuk->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada data.</p>
            @else
                <div class="space-y-1">
                    @foreach ($this->jamTersibuk as $baris)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-14 shrink-0 text-gray-500">{{ sprintf('%02d:00', $baris->jam) }}</span>
                            <span class="flex-1 bg-gray-100 dark:bg-gray-700 rounded h-3 overflow-hidden">
                                <span class="block bg-success-500 h-3"
                                      style="width: {{ round($baris->jumlah / $maksJam * 100) }}%"></span>
                            </span>
                            <span class="w-10 text-right">{{ $baris->jumlah }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::card>

        <x-filament::card>
            <h3 class="text-lg font-bold mb-3">Produk Terlaris</h3>
            @if ($this->produkTerlaris->isEmpty())
                <p class="text-sm text-gray-500">Belum ada penjualan produk.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-gray-500 border-b">
                        <tr>
                            <th class="text-left py-1">Produk</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->produkTerlaris as $baris)
                            <tr class="border-b last:border-0">
                                <td class="py-1">{{ $baris->produk }}</td>
                                <td class="text-right">{{ $baris->qty }}</td>
                                <td class="text-right font-medium">{{ rupiah((int) $baris->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::card>
    </div>
</x-filament::page>
