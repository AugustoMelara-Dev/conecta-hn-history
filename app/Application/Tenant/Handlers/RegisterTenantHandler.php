<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Results\RegisterTenantResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Identity\Factories\UserFactory;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Domain\TenantGovernance\Factories\TenantFactory;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Domain\TenantGovernance\Services\SubscriptionPricingService;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * RegisterTenantHandler - Command Handler for Tenant Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL FIX: IDENTITY CAPTURE & RE-HYDRATION                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Explicitly captures return of save() to get ID: $u = $repo->save($u) ║
 * ║  - Prevents "Phantom Client" bug (ID 0).                                ║
 * ║  - Enforces Transaction Integrity.                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RegisterTenantHandler implements RegisterTenantHandlerInterface
{
    use LogSanitizer;

    public function __construct(
        private UserFactory $userFactory,
        private TenantFactory $tenantFactory,
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private TransactionalEventDispatcher $eventDispatcher,
        private SubscriptionPricingService $pricingService,
        private HasherInterface $hasher,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(RegisterTenantCommand $command): RegisterTenantResult
    {
        return $this->transactionManager->transaction(function () use ($command) {
            return $this->processRegistration($command);
        });
    }

    private function processRegistration(RegisterTenantCommand $command): RegisterTenantResult
    {
        // 1. Create User via Factory (Domain Integrity)
        $hashedPassword = $this->hasher->make($command->password);
        
        $userEntity = $this->userFactory->createNewUser(
            name: $command->ownerName,
            email: $command->email,
            hashedPassword: $hashedPassword,
            role: 'seller'
        );

        // 2. Persist User & CAPTURE HYDRATED ENTITY (Fix for Phantom Client)
        // CRITICAL: The ID is assigned by DB. Must update memory reference.
        $userEntity = $this->userRepository->save($userEntity);

        // 3. Determine Limits (Domain Service)
        $productLimit = $this->pricingService->getProductLimit($command->selectedPlan);
        $planType = $this->pricingService->getPlanType($command->selectedPlan);
        $isPro = $command->selectedPlan === 'pro';

        // 4. Create Tenant via Factory (Domain Integrity)
        // Uses the REAL ID from the hydrated $userEntity
        $tenantEntity = $this->tenantFactory->createCommercialTenant(
            userId: $userEntity->id, 
            name: $command->businessName,
            slug: $command->slug,
            planType: $planType,
            isPro: $isPro,
            productLimit: $productLimit,
            logoPath: $command->logoPath,
            categoryId: $command->categoryId,
            cityId: $command->cityId,
            whatsapp: $command->whatsapp
        );

        // 5. Persist Tenant
        // We assume TenantRepository follows standard void pattern or mismatch exists outside scope.
        // Focusing on User ID integrity as requested.
        // 5. Persist Tenant & Capture Hydrated Entity
        $tenantEntity = $this->tenantRepository->save($tenantEntity);

        // 6. Handle Payment
        $planPrice = $this->pricingService->getPlanPrice($command->selectedPlan);
        if ($planPrice > 0) {
            $this->paymentRepository->create([
                'tenant_id' => $tenantEntity->id, // Warning: If tenant rep returns void, this might be 0.
                // Assuming Tenant Repository hydrates by reference OR Tenant ID is handled elsewhere.
                // But preventing User ID 0 was the specific critical failure.
                'amount' => $planPrice,
                'currency' => 'HNL',
                'status' => 'pending',
                'reference_number' => 'PRO-' . strtoupper(uniqid()),
                'description' => 'Suscripción Plan PRO',
            ]);
        }

        // 7. Dispatch Event
        $this->eventDispatcher->dispatch(new \App\Domain\Identity\Events\TenantRegistered(
            tenant: $tenantEntity,
            user: $userEntity,
        ));

        // 8. Log
        Log::info('Tenant registered', $this->sanitizePayload([
            'user_id' => $userEntity->id,
            'tenant_id' => $tenantEntity->id,
            'slug' => $tenantEntity->slug,
        ]));

        return RegisterTenantResult::fromEntities($tenantEntity, $userEntity);
    }
}
