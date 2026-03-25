<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

class CustomerTouchpointGovernance
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{template_key: string, daily_limit: int, retry_limit: int, retry_backoff_minutes: int}
     */
    public function definition(string $automationKey, string $channel, array $options = []): array
    {
        $defaults = (array) config('customer-automation.governance.defaults', []);
        $channelDefaults = (array) config("customer-automation.governance.channels.{$channel}", []);
        $templateDefaults = (array) config("customer-automation.governance.templates.{$automationKey}", []);

        $templateKey = (string) ($options['template_key']
            ?? $options['template_name']
            ?? $templateDefaults['template_key']
            ?? $automationKey);

        return [
            'template_key' => str($templateKey)->trim()->replace(' ', '_')->lower()->value(),
            'daily_limit' => max(1, (int) ($options['daily_limit']
                ?? $templateDefaults['daily_limit']
                ?? $channelDefaults['daily_limit']
                ?? $defaults['daily_limit']
                ?? 2)),
            'retry_limit' => max(0, (int) ($options['retry_limit']
                ?? $templateDefaults['retry_limit']
                ?? $channelDefaults['retry_limit']
                ?? $defaults['retry_limit']
                ?? 1)),
            'retry_backoff_minutes' => max(5, (int) ($options['retry_backoff_minutes']
                ?? $templateDefaults['retry_backoff_minutes']
                ?? $channelDefaults['retry_backoff_minutes']
                ?? $defaults['retry_backoff_minutes']
                ?? 30)),
        ];
    }

    public function hasReachedDailyLimit(
        CustomerProfile $profile,
        string $channel,
        string $templateKey,
        int $dailyLimit,
        ?int $ignoreTouchpointId = null,
    ): bool {
        if ($dailyLimit <= 0) {
            return false;
        }

        $query = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $profile->tenant_id)
            ->where('customer_profile_id', (int) $profile->getKey())
            ->where('channel', $channel)
            ->where('template_key', $templateKey)
            ->whereIn('status', [
                CustomerTouchpoint::STATUS_QUEUED,
                CustomerTouchpoint::STATUS_SENT,
                CustomerTouchpoint::STATUS_FAILED,
            ])
            ->where('created_at', '>=', now()->subDay());

        if ($ignoreTouchpointId !== null) {
            $query->whereKeyNot($ignoreTouchpointId);
        }

        return $query->count() >= $dailyLimit;
    }

    public function canRetry(CustomerTouchpoint $touchpoint, ?Carbon $moment = null): bool
    {
        return $touchpoint->isRetryable($moment);
    }

    public function nextRetryAt(CustomerTouchpoint $touchpoint, ?Carbon $baseTime = null): ?Carbon
    {
        $baseTime ??= now();

        if ($touchpoint->remaining_retries <= 0) {
            return null;
        }

        $backoffMinutes = max(
            5,
            (int) data_get($touchpoint->metadata, 'retry_backoff_minutes', config('customer-automation.governance.defaults.retry_backoff_minutes', 30)),
        );

        $currentAttempt = max(1, (int) $touchpoint->attempt_count);

        return $baseTime->copy()->addMinutes($backoffMinutes * $currentAttempt);
    }
}
