<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Filament\App\Resources\CampaignResource;

class CreateCampaign extends CreateTenantRecord
{
    protected static string $resource = CampaignResource::class;

    protected function beforeValidate(): void
    {
        $this->normalizeLegacySubjectField();
    }

    /**
     * Compatibilidad defensiva para clientes con estado Livewire viejo.
     *
     * En builds anteriores la pantalla podia enviar `name` en lugar de
     * `subject`. Si el navegador conserva ese snapshot, lo traducimos al
     * campo real antes de crear el registro para evitar errores 500.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeLegacySubjectPayload($data);
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
