<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\DynamicRecordResource\Pages;

use App\Filament\App\Resources\Dynamic\DynamicRecordResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;
use App\Models\Dynamic\DynamicRecord;
use App\Models\Dynamic\EntityDefinition;
use App\Services\DynamicForms\RecordPersistenceService;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditDynamicRecord extends EditTenantRecord
{
    protected static string $resource = DynamicRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof DynamicRecord) {
            return $record;
        }

        $entity = EntityDefinition::query()->findOrFail((string) $record->entity_definition_id);
        $payload = is_array($data['payload_json'] ?? null) ? $data['payload_json'] : [];

        return app(RecordPersistenceService::class)->upsert(
            entityDefinition: $entity,
            payload: $payload,
            coreAttributes: [
                'tenant_id' => $record->tenant_id,
                'status' => (string) ($data['status'] ?? $record->status),
                'owner_id' => $record->owner_id ?: auth()->id(),
                'location_id' => $data['location_id'] ?? $record->location_id,
            ],
            record: $record,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

