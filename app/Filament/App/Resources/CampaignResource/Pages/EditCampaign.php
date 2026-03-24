<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Filament\App\Resources\Pages\EditTenantRecord;
use App\Filament\App\Resources\CampaignResource;

class EditCampaign extends EditTenantRecord
{
    protected static string $resource = CampaignResource::class;

    protected function beforeValidate(): void
    {
        $this->normalizeLegacySubjectField();
    }

    /**
     * Compatibilidad defensiva para snapshots viejos del navegador.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeLegacySubjectPayload($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function normalizeLegacySubjectField(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $this->data = $this->normalizeLegacySubjectPayload($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLegacySubjectPayload(array $data): array
    {
        if (
            filled($data['name'] ?? null)
            && blank($data['subject'] ?? null)
        ) {
            $data['subject'] = (string) $data['name'];
        }

        unset($data['name']);

        return $data;
    }
}
