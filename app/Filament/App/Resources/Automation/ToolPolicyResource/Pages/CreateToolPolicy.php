<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\ToolPolicyResource\Pages;

use App\Filament\App\Resources\Automation\ToolPolicyResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateToolPolicy extends CreateRecord
{
    protected static string $resource = ToolPolicyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }

        return $data;
    }
}
