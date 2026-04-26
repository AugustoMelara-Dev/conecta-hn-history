<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicPrintTemplateResource\Pages;

use App\Filament\Resources\DynamicPrintTemplateResource;
use App\Filament\Resources\Pages\EditAdminRecord;

class EditDynamicPrintTemplate extends EditAdminRecord
{
    protected static string $resource = DynamicPrintTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}

