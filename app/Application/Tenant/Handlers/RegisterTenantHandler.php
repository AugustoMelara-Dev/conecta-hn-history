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
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        $hashedPassword = $this->hasher->make($command->password);
        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $planType = $this->pricingService->getPlanType($command->selectedPlan);
        $productLimit = $this->pricingService->getProductLimit($command->selectedPlan);
        $isPro = $command->selectedPlan === 'pro';

        $tenantAttributes = [
            'id' => $tenantId,
            'user_id' => null,
            'name' => $command->businessName,
            'slug' => $command->slug,
            'plan' => strtoupper($command->selectedPlan),
            'is_active' => true,
        ];

        $optionalAttributes = [
            'plan_type' => (string) $planType,
            'subscription_status' => 'active',
            'is_pro' => $isPro,
            'product_limit' => $productLimit,
            'logo_path' => $command->logoPath,
            'category_id' => $command->categoryId,
            'city_id' => $command->cityId,
            'whatsapp_number' => $command->whatsapp,
            'business_type' => $command->businessType,
            'commercial_mode' => $command->commercialMode,
            'rubro_key' => $command->rubroKey,
        ];

        foreach ($optionalAttributes as $column => $value) {
            if (Schema::hasColumn('tenants', $column)) {
                $tenantAttributes[$column] = $value;
            }
        }

        $tenant = new Tenant;
        $tenant->forceFill($tenantAttributes);
        $tenant->save();

        $user = new User;
        $user->forceFill([
            'id' => $userId,
            'name' => $command->ownerName,
            'email' => $command->email,
            'password' => $hashedPassword,
            'role' => 'seller',
        ]);
        $user->tenant_id = $tenant->id;
        $user->save();

        $tenant->forceFill([
            'user_id' => $user->id,
        ]);
        $tenant->save();

        // 6. Handle Payment
        $planPrice = $this->pricingService->getPlanPrice($command->selectedPlan);
        if ($planPrice > 0) {
            $this->paymentRepository->create([
                'tenant_id' => $tenant->id,
                'amount' => $planPrice,
                'currency' => 'HNL',
                'status' => 'pending',
                'reference_number' => 'PRO-'.strtoupper(uniqid()),
                'description' => 'Suscripción Plan PRO',
            ]);
        }

        // Legacy TenantRegistered still carries int IDs; UUID tenant onboarding is
        // persisted and logged here until that event contract is upgraded.

        // 8. Log
        Log::info('Tenant registered', $this->sanitizePayload([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'slug' => $tenant->slug,
            'commercial_mode' => $command->commercialMode,
        ]));

        return RegisterTenantResult::fromEntities($tenant, $user);
    }
}
