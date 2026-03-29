<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CouponResource\Pages;

use App\Filament\App\Resources\CouponResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;

class CreateCoupon extends CreateTenantRecord
{
    protected static string $resource = CouponResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
