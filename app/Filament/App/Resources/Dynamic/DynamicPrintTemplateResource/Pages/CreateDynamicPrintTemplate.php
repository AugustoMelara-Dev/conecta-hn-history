<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource\Pages;

use App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class CreateDynamicPrintTemplate extends CreateTenantRecord
{
    protected static string $resource = DynamicPrintTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();

        $data['tenant_id'] = $tenant?->getKey();
        $data['owner_scope'] = 'tenant';
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $modelClass = $this->getModel();

        /** @var Model $record */
        $record = $modelClass::query()->create($data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
