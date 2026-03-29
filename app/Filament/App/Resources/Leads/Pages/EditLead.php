<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Leads\Pages;

use App\Filament\App\Resources\Leads\LeadResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;
use Filament\Actions\DeleteAction;

class EditLead extends EditTenantRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
