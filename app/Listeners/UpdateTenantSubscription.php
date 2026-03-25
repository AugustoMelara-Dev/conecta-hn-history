<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Domain\TenantGovernance\Services\CustomerJourneySignalRecorder;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * UpdateTenantSubscription - Transactional Listener with Zero-Tolerance Enforcement
 *
 * ARCHITECTURAL PATTERNS:
 * - Event-Driven Architecture: Fully decoupled from Payment model
 * - ACID Transactions: All-or-nothing subscription updates
 * - Pessimistic Locking: lockForUpdate() prevents race conditions
 * - Idempotency: Guards against double-processing with explicit state checks
 * - Fail-Fast: Throws exceptions on invalid states, no silent failures
 *
 * SECURITY: Uses row-level database locks to mathematically eliminate
 * race conditions when two admins approve payments simultaneously.
 */
class UpdateTenantSubscription
{
    /**
     * Handle the PaymentApproved event.
     *
     * @throws \RuntimeException If payment state is invalid
     * @throws \Exception If transaction fails
     */
    public function handle(PaymentApproved $event): void
    {
        DB::transaction(function () use ($event) {
            // ═══════════════════════════════════════════════════════════════
            // STEP 1: FETCH PAYMENT WITH LOCK AND VALIDATE STATE
            // Fail-fast: No silent failures, throw on invalid state
            // ═══════════════════════════════════════════════════════════════
            $payment = SubscriptionPayment::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->find($event->paymentId);

            if (! $payment) {
                throw new \RuntimeException(
                    "PaymentApproved: Payment ID {$event->paymentId} not found. Event may be stale."
                );
            }

            // Guard: Prevent double-processing (idempotency)
            if ($payment->subscription_extended_at !== null) {
                Log::warning('PaymentApproved: Idempotency guard triggered - already processed', [
                    'payment_id' => $event->paymentId,
                    'tenant_id' => $event->tenantId()->toString(),
                    'original_extension_at' => $payment->subscription_extended_at,
                ]);

                return; // Idempotent exit - no exception, just skip
            }

            // Guard: Ensure payment is in approved state
            if ($payment->status !== SubscriptionPayment::STATUS_APPROVED) {
                throw new \RuntimeException(
                    "PaymentApproved: Payment #{$event->paymentId} is not approved (status: {$payment->status}). ".
                    'Event dispatch may be corrupted.'
                );
            }

            // ═══════════════════════════════════════════════════════════════
            // STEP 2: ACQUIRE EXCLUSIVE LOCK ON TENANT ROW
            // Pessimistic Locking: Concurrent processes will WAIT here
            // This mathematically eliminates race conditions
            // ═══════════════════════════════════════════════════════════════
            $tenant = Tenant::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->find($event->tenantId()->toString());

            if (! $tenant) {
                throw new \RuntimeException(
                    "PaymentApproved: Tenant ID {$event->tenantId()->toString()} not found. Data integrity compromised."
                );
            }

            // ═══════════════════════════════════════════════════════════════
            // STEP 3: CALCULATE AND APPLY SUBSCRIPTION EXTENSION
            // Base date logic: extend from current end or start fresh from today
            // ═══════════════════════════════════════════════════════════════
            $legacyPlanExpiresAt = Schema::hasColumn('tenants', 'plan_expires_at')
                ? $tenant->getAttribute('plan_expires_at')
                : null;

            $baseDate = $tenant->subscription_ends_at ?? $legacyPlanExpiresAt ?? now();

            // If subscription already expired, start from today
            if ($baseDate->isPast()) {
                $baseDate = now();
            }

            $newEndDate = $baseDate->copy()->addDays($event->daysToAdd);
            $planBeforeUpgrade = (string) ($tenant->getRawOriginal('plan_type') ?? '');

            if ($planBeforeUpgrade === '') {
                $planBeforeUpgrade = $tenant->is_pro ? 'pro' : Tenant::PLAN_SEMILLA;
            }

            $tenantUpdates = [
                'is_active' => true,
                'is_pro' => true,
                'subscription_status' => 'active',
                'subscription_ends_at' => $newEndDate,
                'next_billing_date' => $newEndDate->toDateString(),
                'plan_type' => 'pro',
            ];

            if (Schema::hasColumn('tenants', 'plan_expires_at')) {
                $tenantUpdates['plan_expires_at'] = $newEndDate;
            }

            $tenant->forceFill($tenantUpdates)->save();

            // ═══════════════════════════════════════════════════════════════
            // STEP 4: MARK PAYMENT AS CONSUMED (Idempotency Flag)
            // This is our "exactly-once" guarantee
            // ═══════════════════════════════════════════════════════════════
            $payment->update([
                'subscription_extended_at' => now(),
                'plan_before_upgrade' => $planBeforeUpgrade,
            ]);

            Log::info('PaymentApproved: Subscription extended successfully', [
                'payment_id' => $event->paymentId,
                'tenant_id' => $event->tenantId()->toString(),
                'tenant_name' => $tenant->name,
                'plan_before_upgrade' => $planBeforeUpgrade,
                'days_added' => $event->daysToAdd,
                'new_end_date' => $newEndDate->toDateString(),
                'reviewer_id' => $event->reviewerId,
            ]);

            app(CustomerJourneySignalRecorder::class)->recordForTenantActiveConversations(
                $tenant->fresh(),
                'tenant_upgrade',
                [
                    'payment_id' => (int) $payment->getKey(),
                    'plan_before_upgrade' => $planBeforeUpgrade,
                    'plan_after_upgrade' => 'pro',
                    'days_added' => (int) $event->daysToAdd,
                ],
            );
        }, 3); // 3 retry attempts for deadlock recovery
    }
}
