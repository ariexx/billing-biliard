<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_uuid'] = auth()->id();
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        //sum all order items
        $data['total'] = 0;
        foreach ($data['order_items'] as $orderItem) {
            $data['total'] += $orderItem['price'] * $orderItem['quantity'];
        }
        return $data;
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
