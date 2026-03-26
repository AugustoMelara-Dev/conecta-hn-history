<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Domain\TenantGovernance\Services\CampaignFormDataCoordinator;
use App\Filament\App\Resources\CampaignResource;
use App\Filament\App\Resources\Pages\EditTenantRecord;

class EditCampaign extends EditTenantRecord
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
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->formDataCoordinator()->normalize($data, $this->record);
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

        $this->data = $this->formDataCoordinator()->normalize($this->data, $this->record);
    }

    private function formDataCoordinator(): CampaignFormDataCoordinator
    {
        return app(CampaignFormDataCoordinator::class);
    }
}
