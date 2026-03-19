<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Domain\Billing\DTOs\SubscriptionSwapResult;
use App\Domain\Billing\Entities\PlanEntity;
use App\Domain\Billing\Entities\SubscriptionIntent;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\Exceptions\StaleObjectException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ReconcileSubscriptionIntentsCommand - Autonomous Self-Healing Agent
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PROTOCOL: RETRY-ON-CONFLICT                                             ║
 * ║  1. Fresh Read per Attempt (No Stale Data)                               ║
 * ║  2. Compare-and-Swap Writes (OCC)                                        ║
 * ║  3. Exponential Backoff (Manners)                                        ║
 * ║  4. Contextual Logging (Traceability)                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ReconcileSubscriptionIntentsCommand extends Command
{
    protected $signature = 'billing:reconcile-intents';

    protected $description = 'Scavenge for stuck subscription intents and heal zombie charges using OCC';

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private SubscriptionGatewayInterface $gateway,
        private TenantRepositoryInterface $tenantRepository,
        private PlanRepositoryInterface $planRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Reconciliation Scavenger (OCC Enabled)...');

        // 1. SELECT STUCK INTENTS
        $stuckIntents = DB::table('subscription_intents')
            ->where('status', SubscriptionIntent::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(10))
            ->limit(50)
            ->get();

        if ($stuckIntents->isEmpty()) {
            $this->info('No stuck intents found.');

            return self::SUCCESS;
        }

        foreach ($stuckIntents as $intent) {
            $this->processIntent($intent);
        }

        return self::SUCCESS;
    }

    private function processIntent(object $intent): void
    {
        $this->info("Processing stuck intent: {$intent->id}");

        try {
            // Get Metadata directly from DB for the Gateway Call
            $subscriptionRef = DB::table('tenants')->where('id', $intent->tenant_id)->value('subscription_reference');

            $plan = $this->planRepository->findByIdentifier($intent->plan_identifier);

            if (! $plan) {
                $this->markFailed($intent->id, 'Invalid Plan Identifier');

                return;
            }

            // 2. IDEMPOTENT GATEWAY CHECK / RETRY
            // This is OUTSIDE the transaction loop to avoid holding DB connections during HTTP calls.
            $gatewayResult = $this->gateway->swapPlan(
                subscriptionReference: $subscriptionRef ?? "tenant_{$intent->tenant_id}",
                newPlanExternalId: $plan->externalGatewayId,
            );

            if ($gatewayResult->success) {
                // 3. ATOMIC SETTLEMENT (The Retry Loop)
                $this->healWithRetry($intent, $plan, $gatewayResult);
                $this->info("Healed intent {$intent->id}");
            } else {
                $this->markFailed($intent->id, $gatewayResult->failureReason);
                $this->warn("Marked intent {$intent->id} as FAILED.");
            }

        } catch (Throwable $e) {
            $this->error("Error processing {$intent->id}: ".$e->getMessage());
            Log::error('Reconciliation failed', [
                'intent_id' => $intent->id,
                'tenant_id' => $intent->tenant_id,
                'exception' => $e,
            ]);
        }
    }

    private function healWithRetry(object $intent, PlanEntity $plan, SubscriptionSwapResult $gatewayResult): void
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            // Start Transaction Scope: Covers Intent State + Tenant State
            DB::beginTransaction();

            try {
                // ══════════════════════════════════════════════════════════
                // PHASE A: FRESH READ (CRITICAL)
                // ══════════════════════════════════════════════════════════
                $tenant = $this->tenantRepository->findById($intent->tenant_id);

                if (! $tenant) {
                    throw new \RuntimeException("Tenant {$intent->tenant_id} not found during healing.");
                }

                // ══════════════════════════════════════════════════════════
                // PHASE B: BUSINESS LOGIC
                // ══════════════════════════════════════════════════════════
                $ref = $tenant->subscriptionReference() ?? "tenant_{$intent->tenant_id}";

                $tenant->upgradeToPlan(
                    newPlanId: $plan->id,
                    subscriptionEndsAt: $gatewayResult->newPeriodEnd,
                    subscriptionReference: $ref
                );

                // Update Intent Status
                DB::table('subscription_intents')->where('id', $intent->id)->update([
                    'status' => SubscriptionIntent::STATUS_COMPLETED,
                    'gateway_transaction_id' => $gatewayResult->transactionId,
                    'updated_at' => now(),
                ]);

                // ══════════════════════════════════════════════════════════
                // ATOMIC OPTIMISTIC WRITE (Assertion)
                // ══════════════════════════════════════════════════════════
                $this->tenantRepository->updateOptimistically($tenant);

                DB::commit();

                return;

            } catch (StaleObjectException $e) {
                DB::rollBack();

                // ══════════════════════════════════════════════════════════
                // PHASE C: CONFLICT RESOLUTION
                // ══════════════════════════════════════════════════════════

                // Check Exhaustion
                if ($attempts >= self::MAX_ATTEMPTS) {
                    Log::critical('Concurrency conflict exhaustion. Failed to reconcile Intent.', [
                        'intent_id' => $intent->id,
                        'tenant_id' => $intent->tenant_id,
                        'attempts' => $attempts,
                    ]);
                    throw $e; // Give up
                }

                // Backoff Strategy
                $sleepTime = $attempts * 100000; // 100ms, 200ms
                usleep($sleepTime);

                Log::warning("Concurrency conflict detected. Retrying attempt {$attempts}/".self::MAX_ATTEMPTS, [
                    'intent_id' => $intent->id,
                    'tenant_id' => $intent->tenant_id,
                ]);

                // Loop continues -> Re-reads fresh data -> Tries again

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e; // Fatal error, no retry
            }

        }
    }

    private function markFailed(string $id, ?string $reason): void
    {
        DB::table('subscription_intents')->where('id', $id)->update([
            'status' => SubscriptionIntent::STATUS_FAILED,
            'error_reason' => $reason,
            'updated_at' => now(),
        ]);
    }
}
