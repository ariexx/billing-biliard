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

    /*
     * mutateFormDataBeforeSave() dihapus. Method itu menulis $data['total'] ke
     * tabel orders yang tidak punya kolom total (Unknown column 'total' pada
     * setiap simpan), dan mengiterasi $data['order_items'] -- key yang tidak
     * pernah ada karena repeater-nya bernama 'orderItems' dan berbasis relasi,
     * sehingga selalu dilepas dari $data sebelum sampai ke sini.
     *
     * Total order dihitung oleh App\Models\Order::getTotalAttribute().
     */

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
