<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Contracts\SubscriptionIntentStoreInterface;
use App\Application\Billing\Results\RequestUpgradeResult;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Events\SubscriptionUpgraded;
use App\Domain\Billing\Exceptions\PaymentFailedException;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Domain\Shared\Exceptions\StaleObjectException;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * RequestUpgradeHandler - Level 9 (Deterministic Resolution)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RESILIENCE: DETERMINISTIC IDENTITY RESOLUTION                           ║
 * ║  1. Insert-or-Recover Pattern (MySQL Guard)                              ║
 * ║     - Try to Insert Intent (Unique Index Trap)                           ║
 * ║     - Catch Duplicate -> Recover Existing Intent ID                      ║
 * ║  2. Gateway Call using RECOVERED ID (Stripe sees same key)               ║
 * ║  RESULT: 100% Protection against Double Billing even in Fail-Open mode.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
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
        private SubscriptionIntentStoreInterface $subscriptionIntents,
        private TransactionManagerInterface $transactionManager,
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
        $intent = $this->subscriptionIntents->createOrRecoverPending(
            intentId: $generatedIntentId,
            tenantId: $tenant->id(),
            clientIdempotencyKey: $command->clientIdempotencyKey,
            planIdentifier: $plan->identifier,
            gatewayProvider: 'stripe',
            pendingStatus: 'PENDING',
        );

        $capturedIntentId = $intent->id;

        if ($intent->recovered) {
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
                idempotencyKey: $capturedIntentId // UNIFIED KEY: Always the same for this client request
            );

            if (! $gatewayResult->success) {
                $this->subscriptionIntents->markFailed($capturedIntentId, $gatewayResult->failureReason);
                throw PaymentFailedException::fromGateway($gatewayResult->failureReason ?? 'Unknown error');
            }

            // ═══════════════════════════════════════════════════════════════════
            // PHASE C: THE ATOMIC SETTLEMENT (Optimistic Update)
            // ═══════════════════════════════════════════════════════════════════

            try {
                return $this->transactionManager->transaction(function () use ($capturedIntentId, $command, $gatewayResult, $oldPlanId, $plan, $subscriptionRef, $tenant): RequestUpgradeResult {
                    $this->subscriptionIntents->markCompleted($capturedIntentId, $gatewayResult->transactionId);

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

            } catch (StaleObjectException $e) {
                // Eventual Consistency via Reconciler
                throw new \RuntimeException('System busy. Your upgrade is processing in the background.');
            }

        } catch (\Throwable $e) {
            $this->logger->error('RequestUpgradeHandler failed', [
                'intent_id' => $capturedIntentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
