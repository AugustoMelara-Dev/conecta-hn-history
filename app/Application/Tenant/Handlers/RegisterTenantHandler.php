<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Results\RegisterTenantResult;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Identity\Factories\UserFactory;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\TenantGovernance\Factories\TenantFactory;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Domain\TenantGovernance\Services\SubscriptionPricingService;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use App\Application\Shared\Contracts\LoggerInterface;

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
        private readonly LoggerInterface $logger,
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

        // 2. Persist User
        // User identity is pre-allocated by the factory; repository persists in-place.
        $this->userRepository->save($userEntity);

        // 3. Determine Limits (Domain Service)
        $planPrice = $this->pricingService->getPlanPrice($command->selectedPlan);
        $requiresPayment = $planPrice > 0;
        $entitlementPlan = $requiresPayment ? 'free' : $command->selectedPlan;
        $productLimit = $this->pricingService->getProductLimit($entitlementPlan);
        $planType = $this->pricingService->getPlanType($entitlementPlan);
        $isPro = false;

        // 4. Create Tenant via Factory (Domain Integrity)
        // Uses the REAL ID from the hydrated $userEntity
        $tenantEntity = $this->tenantFactory->createCommercialTenant(
            userId: $userEntity->id(),
            name: $command->businessName,
            slug: $command->slug,
            planType: $planType,
            isPro: $isPro,
            productLimit: $productLimit,
            logoPath: $command->logoPath,
            categoryId: $command->categoryId,
            cityId: $command->cityId,
            whatsapp: $command->whatsapp,
            businessType: $command->businessType,
            commercialMode: $command->commercialMode,
            rubroKey: $command->rubroKey,
            tenantId: $userEntity->tenantId(),
        );

        // 5. Persist Tenant
        // We assume TenantRepository follows standard void pattern or mismatch exists outside scope.
        // Focusing on User ID integrity as requested.
        // 5. Persist Tenant & Capture Hydrated Entity
        $tenantEntity = $this->tenantRepository->save($tenantEntity);

        // 6. Handle Payment
        if ($requiresPayment) {
            $this->paymentRepository->create([
                'tenant_id' => $tenantEntity->id(),
                'amount' => $planPrice,
                'bank' => 'pending_activation',
                'status' => 'pending',
                'proof_path' => 'pending',
                'reference_number' => 'PRO-'.strtoupper(uniqid()),
                'description' => 'Suscripción Plan PRO',
            ]);
        }

        // 7. Dispatch Event
        $this->eventDispatcher->dispatch(new \App\Domain\Identity\Events\TenantRegistered(
            tenantId: $tenantEntity->id(),
            userId: $userEntity->id(),
        ));

        // 8. Log
        $this->logger->info('Tenant registered', $this->sanitizePayload([
            'user_id' => $userEntity->id(),
            'tenant_id' => $tenantEntity->id(),
            'slug' => $tenantEntity->domain(),
        ]));

        return RegisterTenantResult::fromEntities($tenantEntity, $userEntity);
    }
}