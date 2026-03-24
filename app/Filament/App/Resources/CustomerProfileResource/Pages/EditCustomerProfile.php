<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\Pages;

use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;

class EditCustomerProfile extends EditTenantRecord
{
    protected static string $resource = CustomerProfileResource::class;

    public function getSubheading(): string
    {
        return 'Ajusta notas, opt-ins, etiquetas y segmentacion operativa.';
    }
}
