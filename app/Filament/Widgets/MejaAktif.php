<?php

namespace App\Filament\Widgets;

use App\Models\ActiveOrder;
use Filament\Widgets\Widget;

class MejaAktif extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.meja-aktif';

    protected function getViewData(): array
    {
        return [
            'sesi' => ActiveOrder::with('product', 'order')
                ->where('is_active', true)
                ->orderBy('end_at')
                ->get(),
        ];
    }
}
