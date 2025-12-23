<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
class UpdateTenantSubscription implements ShouldQueue
{
    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 5;

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
            $payment = SubscriptionPayment::lockForUpdate()
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
                    'tenant_id' => $event->tenantId,
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
            $tenant = Tenant::lockForUpdate()->find($event->tenantId);

            if (! $tenant) {
                throw new \RuntimeException(
                    "PaymentApproved: Tenant ID {$event->tenantId} not found. Data integrity compromised."
                );
            }

            // ═══════════════════════════════════════════════════════════════
            // STEP 3: CALCULATE AND APPLY SUBSCRIPTION EXTENSION
            // Base date logic: extend from current end or start fresh from today
            // ═══════════════════════════════════════════════════════════════
            $baseDate = $tenant->subscription_ends_at ?? now();

            // If subscription already expired, start from today
            if ($baseDate->isPast()) {
                $baseDate = now();
            }

            $newEndDate = $baseDate->copy()->addDays($event->daysToAdd);

            $tenant->update([
                'is_pro' => true,
                'subscription_status' => 'active',
                'subscription_ends_at' => $newEndDate,
                'plan_type' => 'pro',
            ]);

            // ═══════════════════════════════════════════════════════════════
            // STEP 4: MARK PAYMENT AS CONSUMED (Idempotency Flag)
            // This is our "exactly-once" guarantee
            // ═══════════════════════════════════════════════════════════════
            $payment->update([
                'subscription_extended_at' => now(),
            ]);

            Log::info('PaymentApproved: Subscription extended successfully', [
                'payment_id' => $event->paymentId,
                'tenant_id' => $event->tenantId,
                'tenant_name' => $tenant->name,
                'days_added' => $event->daysToAdd,
                'new_end_date' => $newEndDate->toDateString(),
                'reviewer_id' => $event->reviewerId,
            ]);
        }, 3); // 3 retry attempts for deadlock recovery
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentApproved $event, \Throwable $exception): void
    {
        Log::critical('PaymentApproved: Listener FAILED - manual intervention required', [
            'payment_id' => $event->paymentId,
            'tenant_id' => $event->tenantId,
            'reviewer_id' => $event->reviewerId,
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Notify admin via Slack/Email about failed subscription extension
    }
}
