<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Factories;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\Shared\Contracts\ClockInterface;
use Illuminate\Support\Str;
use DateTimeImmutable;

final readonly class TenantFactory
{
    public function __construct(
        private ClockInterface $clock
    ) {}

    public function createTrialTenant(
        UserEntity $owner, 
        string $source,
        ?string $businessName = null,
        ?string $customSlug = null
    ): TenantEntity
    {
        // 1. Fallback Logic for Business Name
        $finalName = ($businessName !== null && $businessName !== '') 
            ? $businessName 
            : ($owner->name . "'s Store");
        
        // 2. Fallback Logic for Slug (Collision check in Handler)
        $finalSlug = ($customSlug !== null && $customSlug !== '')
            ? $customSlug
            : Str::slug($finalName);

        // 3. Construct Entity with strict source tracking
        return new TenantEntity(
            id: 0,
            userId: $owner->id,
            name: $finalName,
            slug: $finalSlug,
            source: $source, 
            planType: TenantPlan::SEMILLA, // PASSED AS OBJECT
            subscriptionStatus: 'active',
            isActive: true,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function createCommercialTenant(
        int $userId,
        string $name,
        string $slug,
        TenantPlan $planType // STRICTLY ENUM
    ): TenantEntity {
        return new TenantEntity(
            id: 0, 
            userId: $userId,
            name: $name,
            slug: $slug,
            source: 'commercial_onboarding',
            planType: $planType, // PASSED AS OBJECT
            subscriptionStatus: 'active',
            isActive: true,
            createdAt: $this->clock->now(),
            updatedAt: $this->clock->now(),
        );
    }
}
