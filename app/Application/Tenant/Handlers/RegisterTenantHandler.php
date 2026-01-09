<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Results\RegisterTenantResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * RegisterTenantHandler - Command Handler for Tenant Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal + Transactional Outbox       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO DB::transaction (handled by Bus Middleware)                        ║
 * ║  - NO App\Models imports (uses Repository interfaces only)              ║
 * ║  - Events dispatched via TransactionalEventDispatcher (Outbox pattern)  ║
 * ║  - STRICT RETURN: RegisterTenantResult DTO (no array leak)              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RegisterTenantHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
        private readonly GeneralSettings $settings,
    ) {}

    /**
     * Handle the command.
     *
     * @param RegisterTenantCommand $command
     * @return RegisterTenantResult
     */
    public function handle(RegisterTenantCommand $command): RegisterTenantResult
    {
        // 1. Create User (Domain Entity creation delegated to Repository)
        $user = $this->userRepository->create([
            'name' => $command->ownerName,
            'email' => $command->email,
            'password' => Hash::make($command->password),
            'role' => 'seller',
        ]);

        // Assign role if using Spatie Permissions (Abstraction Check)
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('seller');
        }

        // 2. Determine Product Limits (Business Logic)
        $productLimit = $command->selectedPlan === 'pro'
            ? ($this->settings->pro_plan_product_limit ?? 100)
            : ($this->settings->free_plan_product_limit ?? 10);

        // 3. Create Tenant (Domain Entity creation delegated to Repository)
        $tenant = $this->tenantRepository->create([
            'name' => $command->businessName,
            'slug' => $command->slug,
            'user_id' => $user->id,
            'logo_path' => $command->logoPath, // Now passed via Command
            'category_id' => $command->categoryId,
            'city_id' => $command->cityId,
            'whatsapp_number' => $command->whatsapp,
            'status' => 'active',
            'is_active' => true,
            'is_pro' => $command->selectedPlan === 'pro',
            'plan_type' => $command->selectedPlan === 'pro' ? 'pro' : 'semilla',
            'subscription_status' => 'active', // Default trial/active status
            'product_limit' => $productLimit,
        ]);

        // 4. Handle Pending Payment for PRO Plan
        $payment = null;
        if ($command->selectedPlan === 'pro') {
            $payment = $this->paymentRepository->create([
                'tenant_id' => $tenant->id,
                'amount' => $this->settings->pro_plan_price ?? 500.00,
                'currency' => 'HNL',
                'status' => 'pending',
                'reference_number' => 'PRO-' . strtoupper(uniqid()),
                'description' => 'Suscripción Plan PRO',
            ]);
        }

        // 5. Dispatch Domain Event (Transactional Outbox)
        // Critical: This MUST happen after persistence but before commit (handled by Dispatcher)
        $this->eventDispatcher->dispatch(new \App\Domain\Identity\Events\TenantRegistered(
            tenant: $tenant,
            user: $user,
        ));

        // 6. Audit Log (PII Scrubbed)
        Log::info('Tenant registered successfully', $this->sanitizePayload([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'slug' => $tenant->slug,
            'plan' => $command->selectedPlan,
            'email' => $command->email, // Will be redacted
        ]));

        return RegisterTenantResult::fromEntities($tenant, $user);
    }
}

