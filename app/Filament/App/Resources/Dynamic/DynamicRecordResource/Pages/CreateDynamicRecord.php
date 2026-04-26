<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\DynamicRecordResource\Pages;

use App\Filament\App\Resources\Dynamic\DynamicRecordResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Models\Dynamic\EntityDefinition;
use App\Services\DynamicForms\RecordPersistenceService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateDynamicRecord extends CreateTenantRecord
{
    protected static string $resource = DynamicRecordResource::class;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $entityId = (string) ($data['entity_definition_id'] ?? '');
        $payload = is_array($data['payload_json'] ?? null) ? $data['payload_json'] : [];

        $entity = EntityDefinition::query()->findOrFail($entityId);

        return app(RecordPersistenceService::class)->upsert(
            entityDefinition: $entity,
            payload: $payload,
            coreAttributes: [
                'tenant_id' => $tenant?->getKey(),
                'status' => (string) ($data['status'] ?? config('dynamic-forms.default_record_status', 'active')),
                'owner_id' => auth()->id(),
                'location_id' => $data['location_id'] ?? null,
            ],
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

