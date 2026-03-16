<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Domain\Access\Enums\Role;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Results\RegisterTenantResult;
use App\Domain\Identity\Events\TenantRegistered;
use App\Domain\Identity\Factories\UserFactory;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\TenantGovernance\Factories\TenantFactory;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Domain\TenantGovernance\Services\SubscriptionPricingService;
use App\Models\Location;
use App\Models\TenantMembership;

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
        private EventDispatcherInterface $eventDispatcher,
        private SubscriptionPricingService $pricingService,
        private HasherInterface $hasher,
        private TransactionManagerInterface $transactionManager,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
        private string $appUrl = '',
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
        // UserEntity already carries a pre-allocated UUID, so the owner ID is safe to propagate.
        $this->userRepository->save($userEntity);

        // 3. Determine Limits (Domain Service)
        $productLimit = $this->pricingService->getProductLimit($command->selectedPlan);
        $planType = $this->pricingService->getPlanType($command->selectedPlan);
        $isPro = $command->selectedPlan === 'pro';

        // 4. Create Tenant via Factory (Domain Integrity)
        $tenantEntity = $this->tenantFactory->createCommercialTenant(
            name: $command->businessName,
            slug: $command->slug,
            ownerId: $userEntity->id(),
            planType: $planType,
            isPro: $isPro,
            productLimit: $productLimit,
            logoPath: $command->logoPath,
            categoryId: $command->categoryId,
            cityId: $command->cityId,
            whatsapp: $command->whatsapp
        );

        // 5. Persist Tenant & Capture Hydrated Entity
        $tenantEntity = $this->tenantRepository->save($tenantEntity);

        // 5.1 Link User to Tenant (Resolves Foreign Key constraint)
        $userEntity->assignToTenant($tenantEntity->id());
        $this->userRepository->save($userEntity);

        // 5.2 Bootstrap RBAC membership (owner of the newly created tenant)
        // Pragmatic bridge: we persist the RBAC membership directly until
        // the access context gets its own repository port.
        TenantMembership::query()->updateOrCreate(
            [
                'tenant_id' => $tenantEntity->id(),
                'user_id' => $userEntity->id(),
            ],
            [
                'role' => Role::OWNER->value,
                'accepted_at' => now(),
            ]
        );

        Location::query()->updateOrCreate(
            [
                'tenant_id' => $tenantEntity->id(),
                'slug' => 'principal',
            ],
            [
                'name' => 'Principal',
                'is_primary' => true,
                'is_active' => true,
            ]
        );

        // 6. Handle Payment (DELEGATED TO PROCESS MANAGER)
        // Billing setup is now eventually consistent to prevent cross-context DB locking.

        // 7. Dispatch Event
        $this->eventDispatcher->dispatch(new TenantRegistered(
            tenantId: $tenantEntity->id(),
            userId: $userEntity->id(),
            selectedPlan: $command->selectedPlan,
        ));

        // 8. Log
        $this->logger->info('Tenant registered', $this->sanitizePayload([
            'user_id' => $userEntity->id(),
            'tenant_id' => $tenantEntity->id(),
            'slug' => $tenantEntity->domain(),
        ], $this->scrubber));

        return RegisterTenantResult::fromEntities($tenantEntity, $userEntity, $this->appUrl);
    }
}
