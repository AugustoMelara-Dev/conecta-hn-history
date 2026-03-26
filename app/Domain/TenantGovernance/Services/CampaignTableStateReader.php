<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class CampaignTableStateReader
{
    /**
     * @return array<string, string>
     */
    public function statusFilterOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'scheduled' => 'Programada',
            'queued' => 'En cola',
            'sending' => 'Enviando',
            'sent' => 'Enviada',
            'failed' => 'Fallida',
        ];
    }

    public function statusTone(string $state): string
    {
        return match ($state) {
            'sending' => 'info',
            'queued' => 'warning',
            'scheduled' => 'warning',
            'failed' => 'danger',
            'sent' => 'success',
            default => 'gray',
        };
    }
}
