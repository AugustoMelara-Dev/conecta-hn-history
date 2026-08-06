<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\NoteResource\Pages;

use App\Filament\App\Resources\Crm\NoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
            $data['created_by'] = $data['created_by'] ?? auth()->id();
        }
        return $data;
    }
}
