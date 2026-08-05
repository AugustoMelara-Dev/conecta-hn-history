<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmPipelineResource\Pages;

use App\Filament\App\Resources\CrmPipelineResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Models\CrmPipeline;
use Illuminate\Support\Str;

class CreateCrmPipeline extends CreateTenantRecord
{
    protected static string $resource = CrmPipelineResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = Str::slug((string) ($data['key'] ?: $data['name']));

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof CrmPipeline) {
            CrmPipelineResource::normalizeDefaultPipeline($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
