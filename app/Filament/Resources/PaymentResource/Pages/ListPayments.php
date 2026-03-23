<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\Pages\ListAdminRecords;
use App\Filament\Resources\PaymentResource;

class ListPayments extends ListAdminRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
