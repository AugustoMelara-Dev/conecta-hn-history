<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Factories;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use DateTimeImmutable;
use Illuminate\Support\Str;

final readonly class TenantFactory
{
    public function __construct(
        private ClockInterface $clock
    ) {}

    public function createTrialTenant(
        string $source,
        string $ownerName, // Replaced UserEntity $owner to avoid tight coupling
        ?string $businessName = null,
        ?string $customSlug = null
    ): TenantEntity {
        $finalName = ($businessName !== null && $businessName !== '')
            ? $businessName
            : ($ownerName."'s Store");

        $finalSlug = ($customSlug !== null && $customSlug !== '')
            ? $customSlug
            : Str::slug($finalName);

        $tenant = TenantEntity::create(
            id: (string) Str::orderedUuid(),
            name: $finalName,
            domain: $finalSlug
        );
        $tenant->upgradeToPlan(TenantPlan::SEMILLA->value);

        return $tenant;
    }

    public function createCommercialTenant(
        string $name,
        string $slug,
        TenantPlan $planType,
        bool $isPro = false,
        int $productLimit = 10,
        ?string $logoPath = null,
        ?int $categoryId = null,
        ?int $cityId = null,
        ?string $whatsapp = null,
    ): TenantEntity {
        $tenant = TenantEntity::create(
            id: (string) Str::orderedUuid(),
            name: $name,
            domain: $slug
        );
        $tenant->upgradeToPlan($planType->value);

        return $tenant;
    }
}
