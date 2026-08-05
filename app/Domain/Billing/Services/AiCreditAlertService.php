<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AiCreditUsageThresholdNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class AiCreditAlertService
{
    public function __construct(
        private readonly AiCreditUsageService $usage,
    ) {}

    public function recordCrossedThresholds(Tenant $tenant): ?int
    {
        $status = $this->usage->forTenant($tenant);
        $thresholds = array_values(array_unique(array_map(
            'intval',
            (array) config('subscriptions.usage_alert_thresholds', [70, 90, 100]),
        )));
        sort($thresholds);

        $highestNewThreshold = null;
        foreach ($thresholds as $threshold) {
            if ($threshold < 1 || $threshold > 100 || $status['percentage'] < $threshold) {
                continue;
            }

            $inserted = DB::table('ai_credit_usage_alerts')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => (string) $tenant->getKey(),
                'billing_period' => $status['period'],
                'threshold' => $threshold,
                'usage_percentage' => $status['percentage'],
                'created_at' => now(),
            ]);

            if ($inserted === 1) {
                $highestNewThreshold = $threshold;
            }
        }

        if ($highestNewThreshold === null) {
            return null;
        }

        $ownerId = (string) $tenant->getAttribute('user_id');
        $percentage = $status['percentage'];
        $period = $status['period'];
        $url = '/app/'.rawurlencode((string) $tenant->getAttribute('slug')).'/manage-billing';

        DB::afterCommit(static function () use ($ownerId, $highestNewThreshold, $percentage, $period, $url): void {
            $owner = User::query()->find($ownerId);
            if ($owner instanceof User) {
                Notification::send($owner, new AiCreditUsageThresholdNotification(
                    threshold: $highestNewThreshold,
                    percentage: $percentage,
                    period: $period,
                    url: $url,
                ));
            }
        });

        return $highestNewThreshold;
    }
}
