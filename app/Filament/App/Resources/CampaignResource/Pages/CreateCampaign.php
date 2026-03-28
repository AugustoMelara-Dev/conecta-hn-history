<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Application\TenantGovernance\Campaigns\Commands\NormalizeCampaignFormDataCommand;
use App\Application\TenantGovernance\Campaigns\Handlers\NormalizeCampaignFormDataHandler;
use App\Filament\App\Resources\CampaignResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;
use Filament\Facades\Filament;
use App\Models\Tenant;

class CreateCampaign extends CreateTenantRecord
{
    protected static string $resource = CampaignResource::class;

    protected function beforeValidate(): void
    {
        $this->normalizeLegacySubjectField();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeFormData($data);
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

        $this->data = $this->normalizeFormData($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeFormData(array $data): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return $data;
        }

        return app(NormalizeCampaignFormDataHandler::class)->handle(
            new NormalizeCampaignFormDataCommand(
                tenantId: (string) $tenant->getKey(),
                data: $data,
            ),
        );
    }
}
