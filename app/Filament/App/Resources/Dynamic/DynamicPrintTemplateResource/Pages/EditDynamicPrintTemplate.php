<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource\Pages;

use App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class EditDynamicPrintTemplate extends EditTenantRecord
{
    protected static string $resource = DynamicPrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['updated_by'] = auth()->id();

        $record->forceFill($data)->save();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

