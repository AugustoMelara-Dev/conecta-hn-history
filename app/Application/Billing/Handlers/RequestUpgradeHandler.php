<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Results\RequestUpgradeResult;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Entities\SubscriptionIntent;
use App\Domain\Billing\Events\SubscriptionUpgraded;
use App\Domain\Billing\Exceptions\PaymentFailedException;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Domain\Shared\Exceptions\StaleObjectException;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * RequestUpgradeHandler - Level 9 (Deterministic Resolution)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RESILIENCE: DETERMINISTIC IDENTITY RESOLUTION                           ║
 * ║  1. Insert-or-Recover Pattern (via SubscriptionIntentRepository)         ║
 * ║     - Try to Insert Intent (Repository handles Unique Index Trap)        ║
 * ║     - If duplicate → Recover Existing Intent ID                          ║
 * ║  2. Gateway Call using RECOVERED ID (Stripe sees same key)               ║
 * ║  RESULT: 100% Protection against Double Billing even in Fail-Open mode.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Replaced ConnectionInterface + QueryException with
 * SubscriptionIntentRepositoryInterface. All raw DB access now lives in
 * the Infrastructure layer's repository implementation.
 */
final readonly class RequestUpgradeHandler implements RequestUpgradeHandlerInterface
{
    use LogSanitizer;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private SubscriptionGatewayInterface $subscriptionGateway,
        private EventDispatcherInterface $eventDispatcher,
        private PlanRepositoryInterface $planRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
        private SubscriptionIntentRepositoryInterface $intentRepository,
        private DataScrubberInterface $scrubber,
    ) {}

    public function handle(RequestUpgradeCommand $command): RequestUpgradeResult
    {
        // 1. VALIDATION
        $plan = $this->planRepository->findByIdentifier($command->newPlanIdentifier);
        if ($plan === null) {
            throw new \InvalidArgumentException("Invalid plan: {$command->newPlanIdentifier}");
        }

        $tenant = $this->tenantRepository->findById($command->tenantId);
        if ($tenant === null) {
            throw TenantNotFoundException::withId($command->tenantId);
        }

        $subscriptionRef = $tenant->subscriptionReference() ?? "tenant_{$tenant->id()}";
        $oldPlanId = $tenant->planId();

        // ═══════════════════════════════════════════════════════════════════
        // PHASE A: DETERMINISTIC RESOLUTION (Insert-or-Recover)
        // ═══════════════════════════════════════════════════════════════════

        $generatedIntentId = $this->uuidGenerator->generate();
        $capturedIntentId = null;

        $inserted = $this->intentRepository->insertOrIgnore([
            'id' => $generatedIntentId,
            'tenant_id' => $tenant->id(),
            'client_idempotency_key' => $command->clientIdempotencyKey,
            'plan_identifier' => $plan->identifier,
            'gateway_provider' => 'stripe',
            'status' => SubscriptionIntent::STATUS_PENDING,
            'created_at' => \Carbon\CarbonImmutable::now(),
            'updated_at' => \Carbon\CarbonImmutable::now(),
        ]);

        if ($inserted) {
            $capturedIntentId = $generatedIntentId;
        } else {
            // RECOVER: We are the second (Race Condition or Retry).
            $existing = $this->intentRepository->findByTenantAndIdempotencyKey(
                $tenant->id(),
                $command->clientIdempotencyKey,
            );

            if (! $existing) {
                throw new \RuntimeException('Race condition anomaly: Duplicate detected but row not found.');
            }

            $capturedIntentId = $existing->id;

            $this->logger->info('Recovered existing intent for double-submit protection', [
                'client_key' => $command->clientIdempotencyKey,
                'intent_id' => $capturedIntentId,
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE B: THE IDEMPOTENT EXECUTION (Unified Identity)
        // ═══════════════════════════════════════════════════════════════════
        try {
            $gatewayResult = $this->subscriptionGateway->swapPlan(
                subscriptionReference: $subscriptionRef,
                newPlanId: $plan->externalGatewayId,
                idempotencyKey: $capturedIntentId
            );

            if (! $gatewayResult->success) {
                $this->intentRepository->updateStatus($capturedIntentId, [
                    'status' => SubscriptionIntent::STATUS_FAILED,
                    'error_reason' => $gatewayResult->failureReason,
                ]);
                throw PaymentFailedException::fromGateway($gatewayResult->failureReason ?? 'Unknown error');
            }

            // ═══════════════════════════════════════════════════════════════════
            // PHASE C: THE ATOMIC SETTLEMENT (Optimistic Update)
            // ═══════════════════════════════════════════════════════════════════

            return $this->transactionManager->transaction(function () use ($capturedIntentId, $command, $tenant, $plan, $gatewayResult, $oldPlanId, $subscriptionRef) {
                $this->intentRepository->updateStatus($capturedIntentId, [
                    'status' => SubscriptionIntent::STATUS_COMPLETED,
                    'gateway_transaction_id' => $gatewayResult->transactionId,
                ]);

                $tenant->upgradeToPlan(
                    newPlanId: $plan->id,
                    subscriptionEndsAt: $gatewayResult->newPeriodEnd,
                    subscriptionReference: $subscriptionRef
                );

                $this->tenantRepository->updateOptimistically($tenant);

                $this->eventDispatcher->dispatch(SubscriptionUpgraded::create(
                    tenantId: $command->tenantId,
                    oldPlanId: $oldPlanId ?? 'free',
                    newPlanId: $plan->id,
                    newPlanName: $plan->name,
                    transactionId: $gatewayResult->transactionId,
                ));

                return RequestUpgradeResult::fromTenantState(
                    tenantId: $command->tenantId,
                    newPlanId: $plan->id,
                    endsAt: $gatewayResult->newPeriodEnd
                );
            });

        } catch (\Throwable $e) {
            $this->logger->error('RequestUpgradeHandler failed', [
                'intent_id' => $capturedIntentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
