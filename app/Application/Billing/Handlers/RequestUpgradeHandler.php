<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Events\SubscriptionUpgraded;
use App\Domain\Billing\Exceptions\PaymentFailedException;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * RequestUpgradeHandler - Command Handler for Plan Upgrade Requests
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY AGNOSTIC: Uses SubscriptionGatewayInterface abstraction.       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO Stripe/Cashier/PayPal references (all in Infrastructure)         ║
 * ║  - Local DB update ONLY after gateway confirmation                      ║
 * ║  - Dispatches SubscriptionUpgraded for downstream processing            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RequestUpgradeHandler
{
    use LogSanitizer;

    /**
     * Plan configuration (pricing and external IDs).
     * In production, this should come from database or config.
     */
    private const PLANS = [
        'pro' => ['id' => 2, 'price' => 499, 'external_id' => 'plan_pro'],
        'premium' => ['id' => 3, 'price' => 999, 'external_id' => 'plan_premium'],
    ];

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Handle upgrade request command.
     *
     * @param RequestUpgradeCommand $command
     * @return void
     * @throws ModelNotFoundException If tenant not found
     * @throws PaymentFailedException If gateway fails
     */
    public function handle(RequestUpgradeCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // STEP 1: LOAD AND VALIDATE
        // ═══════════════════════════════════════════════════════════════════
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw new ModelNotFoundException("Tenant not found: {$command->tenantId}");
        }

        // Validate plan exists
        $planConfig = self::PLANS[$command->newPlanIdentifier] ?? null;
        if ($planConfig === null) {
            throw new \InvalidArgumentException("Invalid plan: {$command->newPlanIdentifier}");
        }

        // Guard: Already on this plan?
        $oldPlanId = $tenant->plan_id ?? 1; // Default to free plan
        if ($oldPlanId === $planConfig['id']) {
            Log::info('Tenant already on requested plan', [
                'tenant_id' => $command->tenantId,
                'plan' => $command->newPlanIdentifier,
            ]);
            return; // Idempotent
        }

        // ═══════════════════════════════════════════════════════════════════
        // STEP 2: EXECUTE EXTERNAL OPERATION (Gateway)
        // ═══════════════════════════════════════════════════════════════════
        $subscriptionRef = $tenant->subscription_reference ?? "tenant_{$tenant->id}";
        
        $result = $this->subscriptionGateway->swapPlan(
            $subscriptionRef,
            $planConfig['external_id']
        );

        // ═══════════════════════════════════════════════════════════════════
        // STEP 3: GUARD - GATEWAY FAILURE
        // ═══════════════════════════════════════════════════════════════════
        if (!$result->success) {
            Log::warning('Subscription upgrade failed at gateway', [
                'tenant_id' => $command->tenantId,
                'plan' => $command->newPlanIdentifier,
                'reason' => $result->failureReason,
            ]);
            throw PaymentFailedException::fromGateway(
                $result->failureReason ?? 'Unknown gateway error'
            );
        }

        // ═══════════════════════════════════════════════════════════════════
        // STEP 4: SYNCHRONIZE LOCAL STATE (Only after gateway success)
        // ═══════════════════════════════════════════════════════════════════
        $tenant->plan_id = $planConfig['id'];
        $tenant->subscription_ends_at = $result->newPeriodEnd;
        $tenant->subscription_reference = $subscriptionRef;
        
        $this->tenantRepository->save($tenant);

        // ═══════════════════════════════════════════════════════════════════
        // STEP 5: DISPATCH DOMAIN EVENT
        // ═══════════════════════════════════════════════════════════════════
        $this->eventDispatcher->dispatch(SubscriptionUpgraded::create(
            tenantId: $command->tenantId,
            oldPlanId: $oldPlanId,
            newPlanId: $planConfig['id'],
            newPlanName: $command->newPlanIdentifier,
            transactionId: $result->transactionId,
        ));

        Log::info('Subscription upgraded successfully', $this->sanitizePayload([
            'tenant_id' => $command->tenantId,
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => $planConfig['id'],
            'transaction_id' => $result->transactionId,
        ]));
    }
}
