<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CouponResource\Pages;

use App\Filament\App\Resources\CouponResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListCoupons extends ListTenantRecords
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
