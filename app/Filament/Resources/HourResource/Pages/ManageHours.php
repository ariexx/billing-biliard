<?php

namespace App\Filament\Resources\HourResource\Pages;

use App\Filament\Resources\HourResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageHours extends ManageRecords
{
    protected static string $resource = HourResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
