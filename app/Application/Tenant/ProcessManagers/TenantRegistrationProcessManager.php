<?php

declare(strict_types=1);

namespace App\Application\Tenant\ProcessManagers;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Identity\Events\TenantRegistered;
use App\Domain\TenantGovernance\Services\SubscriptionPricingService;
use Exception;

final readonly class TenantRegistrationProcessManager
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private SubscriptionPricingService $pricingService,
        private LoggerInterface $logger,
    ) {}

    public function handle(TenantRegistered $event): void
    {
        try {
            // Note: Since the event currently only carries IDs, the Process Manager
            // needs a way to know the selected plan. In a fully decoupled Saga,
            // the TenantRegistered event should include the 'planType' or 'selectedPlan'.
            // For now, this is a placeholder structural decoupling. The handler will be
            // updated to pass the necessary data, or we retrieve the tenant here.

            // TODO: In Phase 2, implement the actual Saga state machine and compensation logic.
            $this->logger->info('Process Manager: Acknowledged Tenant Registration', [
                'tenant_id' => $event->tenantId,
                'user_id' => $event->userId,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Process Manager: Failed to handle Tenant Registration', [
                'error' => $e->getMessage(),
                'tenant_id' => $event->tenantId,
            ]);
        }
    }
}
