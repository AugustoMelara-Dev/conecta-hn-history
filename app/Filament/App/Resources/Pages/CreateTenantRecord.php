<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Pages;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class CreateTenantRecord extends CreateRecord
{
    public function getTitle(): string
    {
        return 'Crear '.Str::lower($this->getResource()::getModelLabel());
    }

    public function getBreadcrumb(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        unset(
            $data['id'],
            $data['tenant_id'],
            $data['created_at'],
            $data['updated_at'],
            $data['deleted_at'],
        );

        $record = new ($this->getModel())();
        $record->forceFill($data);
        $tenant = Filament::getTenant();

        if (
            $tenant instanceof Model &&
            in_array(BelongsToTenant::class, class_uses_recursive($record), true)
        ) {
            $record->tenant_id = $tenant->getKey();
        }

        if ($parentRecord = $this->getParentRecord()) {
            return $this->associateRecordWithParent($record, $parentRecord);
        }

        $record->save();

        return $record;
    }
}
