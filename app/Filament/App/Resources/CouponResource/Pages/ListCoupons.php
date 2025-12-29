<?php

namespace App\Filament\App\Resources\CouponResource\Pages;

use App\Filament\App\Resources\CouponResource;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nuevo Cupón'),
        ];
    }
}
