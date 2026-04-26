<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicPrintTemplateResource\Pages;

use App\Filament\Resources\DynamicPrintTemplateResource;
use App\Filament\Resources\Pages\CreateAdminRecord;

class CreateDynamicPrintTemplate extends CreateAdminRecord
{
    protected static string $resource = DynamicPrintTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}

