<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Exceptions\InsufficientAiCreditsException;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AiCreditUsageService
{
    public function __construct(
        private readonly PlanEntitlementService $entitlements,
    ) {}

    /**
     * @return array{
     *   plan: string, allowance: float, daily_limit: float, spent: float,
     *   spent_today: float, reserved: float, reserved_today: float, available: float, monthly_remaining: float, percentage: int,
     *   threshold: int, status: string, period: string, resets_at: string
     * }
     */
    public function forTenant(Tenant $tenant, ?Carbon $now = null): array
    {
        $now ??= now();
        $snapshot = $this->entitlements->forTenant($tenant);
        $periodStart = $now->copy()->utc()->startOfMonth();
        $dayStart = $now->copy()->utc()->startOfDay();

        $spent = (float) DB::table('ai_credit_reservations')
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('denomination', 'commercial_credits')
            ->where('status', 'settled')
            ->where('settled_at', '>=', $periodStart)
            ->sum('actual_cost');

        $spentToday = (float) DB::table('ai_credit_reservations')
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('denomination', 'commercial_credits')
            ->where('status', 'settled')
            ->where('settled_at', '>=', $dayStart)
            ->sum('actual_cost');

        $reservedToday = (float) DB::table('ai_credit_reservations')
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('denomination', 'commercial_credits')
            ->where('status', 'pending')
            ->where('created_at', '>=', $dayStart)
            ->sum('estimated_cost');

        $allowance = (float) $snapshot->limit('ai_monthly_credits');
        $reserved = (float) $tenant->getAttribute('ai_reserved_balance');
        $monthlyRemaining = max(0, $allowance - $spent - $reserved);
        $materializedAvailable = max(0, (float) $tenant->getAttribute('ai_balance') - (float) $tenant->getAttribute('ai_reserved_balance'));
        $percentage = $allowance > 0
            ? min(100, (int) floor(($spent / $allowance) * 100))
            : ($spent > 0 ? 100 : 0);
        $threshold = $percentage >= 100 ? 100 : ($percentage >= 90 ? 90 : ($percentage >= 70 ? 70 : 0));

        return [
            'plan' => $snapshot->plan->value,
            'allowance' => $allowance,
            'daily_limit' => (float) $snapshot->limit('ai_daily_credits'),
            'spent' => round($spent, 6),
            'spent_today' => round($spentToday, 6),
            'reserved' => round($reserved, 6),
            'reserved_today' => round($reservedToday, 6),
            'available' => round(min($materializedAvailable, $monthlyRemaining), 6),
            'monthly_remaining' => round($monthlyRemaining, 6),
            'percentage' => $percentage,
            'threshold' => $threshold,
            'status' => match ($threshold) {
                100 => 'exhausted',
                90 => 'critical',
                70 => 'warning',
                default => 'healthy',
            },
            'period' => $periodStart->format('Y-m'),
            'resets_at' => $periodStart->copy()->addMonth()->toIso8601String(),
        ];
    }

    public function assertWithinDailyLimit(Tenant $tenant, float $estimatedCost, ?Carbon $now = null): void
    {
        $usage = $this->forTenant($tenant, $now);
        $projected = $usage['spent_today'] + $usage['reserved_today'] + $estimatedCost;
        $monthlyProjected = $usage['spent'] + $usage['reserved'] + $estimatedCost;

        if ($monthlyProjected > $usage['allowance'] + 0.000001) {
            throw InsufficientAiCreditsException::forOperation(
                tenantId: (string) $tenant->getKey(),
                operation: 'monthly_limit',
                estimatedCost: $estimatedCost,
                availableBalance: max(0, $usage['monthly_remaining']),
            );
        }

        if ($usage['daily_limit'] > 0 && $projected > $usage['daily_limit'] + 0.000001) {
            throw InsufficientAiCreditsException::forDailyLimit(
                tenantId: (string) $tenant->getKey(),
                requiredCredits: $estimatedCost,
                remainingDailyCredits: max(0, $usage['daily_limit'] - $usage['spent_today'] - $usage['reserved_today']),
                dailyLimit: $usage['daily_limit'],
            );
        }
    }
}
