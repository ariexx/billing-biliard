<x-filament::widget>
    <x-filament::card>
        <h2 class="text-lg font-bold tracking-tight mb-3">Meja Sedang Dipakai</h2>

        @if ($sesi->isEmpty())
            <p class="text-sm text-gray-500">Tidak ada meja yang sedang dipakai.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500 border-b">
                        <tr>
                            <th class="py-2 pr-4">Meja</th>
                            <th class="py-2 pr-4">Tipe</th>
                            <th class="py-2 pr-4">Mulai</th>
                            <th class="py-2 pr-4">Selesai</th>
                            <th class="py-2">Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sesi as $baris)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-4 font-medium">{{ $baris->product?->name ?? '-' }}</td>
                                <td class="py-2 pr-4">{{ ucwords($baris->hour_type ?? '-') }}</td>
                                <td class="py-2 pr-4">{{ $baris->started_at?->format('H:i') }}</td>
                                <td class="py-2 pr-4">
                                    @if ($baris->hour_type === 'free time')
                                        <span class="text-gray-500">berjalan</span>
                                    @else
                                        {{ $baris->end_at?->format('H:i') }}
                                    @endif
                                </td>
                                <td class="py-2">{{ $baris->order?->order_number ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::card>
</x-filament::widget>
