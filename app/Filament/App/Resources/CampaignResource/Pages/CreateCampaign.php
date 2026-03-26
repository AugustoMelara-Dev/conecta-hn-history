<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Domain\TenantGovernance\Services\CampaignFormNormalizer;
use App\Filament\App\Resources\CampaignResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;

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
        return app(CampaignFormNormalizer::class)->normalize($data);
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

        $this->data = app(CampaignFormNormalizer::class)->normalize($this->data);
    }
}
