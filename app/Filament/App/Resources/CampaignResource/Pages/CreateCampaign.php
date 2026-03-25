<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Filament\App\Resources\CampaignResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;
use Carbon\Carbon;

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
        return $this->normalizeCampaignPayload($this->normalizeLegacySubjectPayload($data));
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
        if (filled($data['name'] ?? null) && blank($data['subject'] ?? null)) {
            $data['subject'] = (string) $data['name'];
        }

        unset($data['name']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeCampaignPayload(array $data): array
    {
        $scheduledAt = filled($data['scheduled_at'] ?? null)
            ? Carbon::parse((string) $data['scheduled_at'])
            : null;
        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        $cadence = max(0, (int) ($data['cadence_interval_days'] ?? 0));

        if (! $isRecurring) {
            $data['cadence_interval_days'] = null;
            $data['next_run_at'] = null;
        }

        if ($scheduledAt instanceof Carbon) {
            $data['scheduled_at'] = $scheduledAt;
        }

        if ($isRecurring) {
            $data['status'] = 'scheduled';
            $data['next_run_at'] = ($scheduledAt ?? now()->addDay())->copy();
        } elseif ($scheduledAt instanceof Carbon && $scheduledAt->isFuture()) {
            $data['status'] = 'scheduled';
            $data['next_run_at'] = null;
        } else {
            $data['status'] = 'draft';
            $data['next_run_at'] = null;
        }

        if ($cadence <= 0) {
            $data['cadence_interval_days'] = null;
        }

        return $data;
    }
}
