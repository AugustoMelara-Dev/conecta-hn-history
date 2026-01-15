<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Results\RequestUpgradeResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Events\SubscriptionUpgraded;
use App\Domain\Billing\Exceptions\PaymentFailedException;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * RequestUpgradeHandler - Command Handler for Plan Upgrade Requests
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY AGNOSTIC: Uses SubscriptionGatewayInterface abstraction.       ║
 * ║  TYPE SAFE: No int casts on IDs - treats all IDs as opaque strings.     ║
 * ║  HEXAGONAL COMPLIANT: No direct Eloquent dependencies in logic.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RequestUpgradeHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        private readonly TransactionalEventDispatcher $eventDispatcher,
        private readonly PlanRepositoryInterface $planRepository,
    ) {}

    /**
     * Handle upgrade request command.
     *
     * @param RequestUpgradeCommand $command
     * @return RequestUpgradeResult
     * @throws ModelNotFoundException If tenant not found
     * @throws PaymentFailedException If gateway fails
     */
    public function handle(RequestUpgradeCommand $command): RequestUpgradeResult
    {
        // ═══════════════════════════════════════════════════════════════════
        // STEP 1: LOAD AND VALIDATE
        // ═══════════════════════════════════════════════════════════════════
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw new ModelNotFoundException("Tenant not found: {$command->tenantId}");
        }

        // Validate plan exists via Repository
        $plan = $this->planRepository->findByIdentifier($command->newPlanIdentifier);
        
        if ($plan === null) {
            throw new \InvalidArgumentException("Invalid plan: {$command->newPlanIdentifier}");
        }

        // Guard: Already on this plan? (String comparison - no int cast)
        $oldPlanId = (string) ($tenant->plan_id ?? '1');
        if ($oldPlanId === $plan->id) {
            Log::info('Tenant already on requested plan', [
                'tenant_id' => $command->tenantId,
                'plan' => $command->newPlanIdentifier,
            ]);
            // Return idempotent success result
            return RequestUpgradeResult::fromTenantState(
                tenantId: $command->tenantId,
                newPlanId: $plan->id,
                endsAt: $tenant->subscription_ends_at
            );
        }

        // ═══════════════════════════════════════════════════════════════════
        // STEP 2: EXECUTE EXTERNAL OPERATION (Gateway)
        // ═══════════════════════════════════════════════════════════════════
        $subscriptionRef = $tenant->subscription_reference ?? "tenant_{$tenant->id}";
        
        $result = $this->subscriptionGateway->swapPlan(
            $subscriptionRef,
            $plan->externalGatewayId
        );

        // ═══════════════════════════════════════════════════════════════════
        // STEP 3: GUARD - GATEWAY FAILURE
        // ═══════════════════════════════════════════════════════════════════
        if (!$result->success) {
            Log::warning('Subscription upgrade failed at gateway', [
                'tenant_id' => $command->tenantId,
                'plan' => $plan->name,
                'reason' => $result->failureReason,
            ]);
            throw PaymentFailedException::fromGateway(
                $result->failureReason ?? 'Unknown gateway error'
            );
        }

        // ═══════════════════════════════════════════════════════════════════
        // STEP 4: SYNCHRONIZE LOCAL STATE (Only after gateway success)
        // Infrastructure handles int conversion if DB requires it
        // ═══════════════════════════════════════════════════════════════════
        $tenant->plan_id = $plan->id;
        $tenant->subscription_ends_at = $result->newPeriodEnd;
        $tenant->subscription_reference = $subscriptionRef;
        
        $this->tenantRepository->save($tenant);

        // ═══════════════════════════════════════════════════════════════════
        // STEP 5: DISPATCH DOMAIN EVENT
        // Event uses string IDs - deserialization handles format
        // ═══════════════════════════════════════════════════════════════════
        $this->eventDispatcher->dispatch(SubscriptionUpgraded::create(
            tenantId: $command->tenantId,
            oldPlanId: $oldPlanId,
            newPlanId: $plan->id,
            newPlanName: $plan->name,
            transactionId: $result->transactionId,
        ));

        Log::info('Subscription upgraded successfully', $this->sanitizePayload([
            'tenant_id' => $command->tenantId,
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => $plan->id,
            'transaction_id' => $result->transactionId,
            'price_cents' => $plan->priceInCents()
        ]));

        // ═══════════════════════════════════════════════════════════════════
        // STEP 6: RETURN CONTRACT RESULT
        // ═══════════════════════════════════════════════════════════════════
        return RequestUpgradeResult::fromTenantState(
            tenantId: $command->tenantId,
            newPlanId: $plan->id,
            endsAt: $result->newPeriodEnd
        );
    }
}
