<x-filament::page>
    <form wire:submit.prevent="simpan" class="space-y-6">
        {{ $this->form }}

        {{-- Diberi garis pemisah dan jarak lega: tanpa itu tombol simpan menempel
             ke kotak form terakhir dan terlihat seperti bagian dari section. --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4
                    pt-6 mt-8 border-t border-gray-200 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Perubahan langsung berlaku. Salinan <code>.env</code> sebelumnya ikut disimpan.
            </p>

            <x-filament::button type="submit" size="lg" wire:loading.attr="disabled" wire:target="simpan">
                <span wire:loading.remove wire:target="simpan">Simpan Pengaturan</span>
                <span wire:loading wire:target="simpan">Menyimpan...</span>
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8"></div>

    <x-filament::card>
        <h3 class="text-lg font-bold mb-1">Backup Tersimpan di PC</h3>
        <p class="text-xs text-gray-500 mb-3">
            Sepuluh terbaru. Backup lama dihapus otomatis sesuai pengaturan di atas.
        </p>

        @if (empty($this->backupLokal))
            <p class="text-sm text-gray-500">
                Belum ada backup. Tekan <b>Backup Sekarang</b> di kanan atas untuk membuat yang pertama.
            </p>
        @else
            <table class="w-full text-sm">
                <thead class="text-gray-500 border-b">
                    <tr>
                        <th class="text-left py-1">Berkas</th>
                        <th class="text-right">Ukuran</th>
                        <th class="text-right">Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->backupLokal as $b)
                        <tr class="border-b last:border-0">
                            <td class="py-1 font-mono text-xs">{{ $b['nama'] }}</td>
                            <td class="text-right">{{ number_format($b['ukuran'], 2, ',', '.') }} MB</td>
                            <td class="text-right">{{ $b['waktu']->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::card>
</x-filament::page>
