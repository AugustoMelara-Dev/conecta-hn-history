<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\AiCreditEntryType;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MonthlyAiCreditGrantService
{
    public function __construct(
        private readonly PlanEntitlementService $entitlements,
        private readonly AiCreditLedger $ledger,
        private readonly AiCreditPricingService $pricing,
    ) {}

    public function grant(Tenant $tenant, ?Carbon $period = null): bool
    {
        $period ??= now();
        $periodKey = $period->copy()->utc()->startOfMonth()->format('Y-m');

        return DB::transaction(function () use ($tenant, $periodKey): bool {
            /** @var Tenant|null $lockedTenant */
            $lockedTenant = Tenant::query()->lockForUpdate()->find($tenant->getKey());
            if (! $lockedTenant instanceof Tenant) {
                throw new \RuntimeException('Tenant not found while granting AI credits.');
            }

            $snapshot = $this->entitlements->forTenant($lockedTenant);

            $latestPlan = DB::table('ai_credit_grants')
                ->where('tenant_id', (string) $lockedTenant->getKey())
                ->where('billing_period', $periodKey)
                ->orderByDesc('transition_sequence')
                ->value('plan');

            if (is_string($latestPlan) && hash_equals($latestPlan, $snapshot->plan->value)) {
                return false;
            }

            $transitionSequence = ((int) DB::table('ai_credit_grants')
                ->where('tenant_id', (string) $lockedTenant->getKey())
                ->where('billing_period', $periodKey)
                ->max('transition_sequence')) + 1;
            $amount = (float) $snapshot->limit('ai_monthly_credits');
            $previousBalance = (float) $lockedTenant->getAttribute('ai_balance');
            $newBalance = max((float) $lockedTenant->getAttribute('ai_reserved_balance'), $amount);
            $grantId = (string) Str::uuid();

            DB::table('ai_credit_grants')->insert([
                'id' => $grantId,
                'tenant_id' => (string) $lockedTenant->getKey(),
                'billing_period' => $periodKey,
                'plan' => $snapshot->plan->value,
                'transition_sequence' => $transitionSequence,
                'amount' => $amount,
                'granted_at' => now()->utc(),
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);

            $lockedTenant->forceFill([
                'ai_balance' => $newBalance,
                'ai_credit_unit_version' => $this->pricing->version(),
            ])->save();

            $this->ledger->append(
                tenantId: (string) $lockedTenant->getKey(),
                type: AiCreditEntryType::MONTHLY_GRANT,
                balanceDelta: $newBalance - $previousBalance,
                reservedDelta: 0,
                idempotencyKey: "monthly-grant:{$grantId}",
                billingPeriod: $periodKey,
                metadata: [
                    'grant_id' => $grantId,
                    'plan' => $snapshot->plan->value,
                    'transition_sequence' => $transitionSequence,
                    'allowance' => $amount,
                    'previous_balance' => $previousBalance,
                    'carry_over' => false,
                ],
            );

            return true;
        }, 3);
    }
}
