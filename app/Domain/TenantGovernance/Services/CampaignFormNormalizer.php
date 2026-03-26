<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Campaign;
use Carbon\Carbon;

final class CampaignFormNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data, ?Campaign $record = null): array
    {
        if (filled($data['name'] ?? null) && blank($data['subject'] ?? null)) {
            $data['subject'] = (string) $data['name'];
        }

        unset($data['name']);

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
        } elseif (($record?->status ?? null) !== 'sending' && ($record?->status ?? null) !== 'sent') {
            $data['status'] = 'draft';
            $data['next_run_at'] = null;
        }

        if ($cadence <= 0) {
            $data['cadence_interval_days'] = null;
        }

        return $data;
    }
}
