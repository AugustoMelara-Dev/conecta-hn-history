<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Results\RegisterTenantResult;
use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Events\TenantRegistered;
use App\Domain\Identity\Factories\UserFactory;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Factories\TenantFactory;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Domain\TenantGovernance\Services\SubscriptionPricingService;
use App\Models\LegalConsent;
use App\Models\Location;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantBusinessClaim;
use App\Models\TenantMembership;
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
        $hasPaidPlan = $command->selectedPlan === 'pro';

        // 4. Create Tenant via Factory (Domain Integrity)
        $tenantEntity = $this->tenantFactory->createCommercialTenant(
            name: $command->businessName,
            slug: $command->slug,
            ownerId: $userEntity->id(),
            planType: $planType,
            hasPaidPlan: $hasPaidPlan,
            productLimit: $productLimit,
            businessType: BusinessType::from($command->businessType),
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
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantEntity->id())
            ->where('user_id', $userEntity->id())
            ->first();

        if (! $membership instanceof TenantMembership) {
            $membership = new TenantMembership;
        }

        $membership->forceFill([
            'tenant_id' => $tenantEntity->id(),
            'user_id' => $userEntity->id(),
            'role' => Role::OWNER->value,
            'accepted_at' => now(),
        ])->save();

        $location = Location::query()
            ->where('tenant_id', $tenantEntity->id())
            ->where('slug', 'principal')
            ->first();

        if (! $location instanceof Location) {
            $location = new Location;
        }

        $location->forceFill([
            'tenant_id' => $tenantEntity->id(),
            'slug' => 'principal',
            'name' => 'Principal',
            'is_primary' => true,
            'is_active' => true,
        ])->save();

        // 5.3 Start claim verification in provisional mode.
        Tenant::query()
            ->whereKey($tenantEntity->id())
            ->update([
                'rubro_key' => $command->rubroKey,
                'business_segment_key' => $command->rubroKey,
                'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
                'verified_at' => null,
                'verification_reviewed_at' => null,
                'verification_rejection_reason' => null,
                'publication_paused_at' => null,
                'publication_pause_reason' => null,
            ]);

        TenantBusinessClaim::query()->updateOrCreate(
            ['tenant_id' => $tenantEntity->id()],
            [
                'user_id' => $userEntity->id(),
                'status' => 'pending',
                'email_verified_at' => null,
                'whatsapp_number' => $command->whatsapp,
                'whatsapp_otp_verified_at' => null,
                'evidence_type' => $command->claimEvidenceType,
                'evidence_url' => $command->claimEvidenceUrl,
                'evidence_notes' => $command->claimEvidenceNotes,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'decision_notes' => null,
            ]
        );

        if ($command->termsAccepted && $command->privacyAccepted) {
            LegalConsent::query()->create([
                'user_id' => $userEntity->id(),
                'tenant_id' => $tenantEntity->id(),
                'terms_version' => (string) ($command->termsVersion ?? config('legal.current_versions.terms')),
                'privacy_version' => (string) ($command->privacyVersion ?? config('legal.current_versions.privacy')),
                'accepted_at' => now(),
                'ip_address' => $command->acceptedIpAddress,
                'user_agent' => $command->acceptedUserAgent,
            ]);
        }

        // 6. Open the payment boundary immediately for paid registrations.
        $paymentId = $this->createPendingPaymentForPaidRegistration(
            tenantId: $tenantEntity->id(),
            businessName: $command->businessName,
            selectedPlan: $command->selectedPlan,
        );

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

        return RegisterTenantResult::fromEntities($tenantEntity, $userEntity, $this->appUrl, $paymentId);
    }

    private function createPendingPaymentForPaidRegistration(
        string $tenantId,
        string $businessName,
        string $selectedPlan,
    ): ?string {
        if ($selectedPlan !== 'pro') {
            return null;
        }

        $priceInLempiras = $this->pricingService->getPlanPrice($selectedPlan);
        $payment = SubscriptionPayment::forceCreate([
            'tenant_id' => $tenantId,
            'amount' => (int) round($priceInLempiras * 100),
            'bank' => 'bank_transfer',
            'reference_number' => 'REG-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'proof_path' => '',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'idempotency_key' => 'registration:'.$tenantId.':'.$selectedPlan,
            'plan_before_upgrade' => 'semilla',
            'admin_notes' => 'Registro Pro pendiente de pago para '.$businessName,
        ]);

        return (string) $payment->getKey();
    }
}
