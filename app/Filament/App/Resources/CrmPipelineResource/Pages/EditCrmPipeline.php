<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmPipelineResource\Pages;

use App\Filament\App\Resources\CrmPipelineResource;
use App\Models\CrmPipeline;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditCrmPipeline extends EditRecord
{
    protected static string $resource = CrmPipelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record instanceof CrmPipeline && CrmPipelineResource::canDelete($this->record)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['key'] = Str::slug((string) ($data['key'] ?: $data['name']));

        return $data;
    }

    protected function afterSave(): void
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
