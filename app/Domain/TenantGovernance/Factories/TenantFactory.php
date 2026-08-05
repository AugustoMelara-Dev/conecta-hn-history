<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Factories;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use Illuminate\Support\Str;

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
    ): TenantEntity {
        $finalName = ($businessName !== null && $businessName !== '')
            ? $businessName
            : ($owner->name()."'s Store");

        $finalSlug = ($customSlug !== null && $customSlug !== '')
            ? $customSlug
            : Str::slug($finalName);

        return TenantEntity::create(
            id: (string) Str::orderedUuid(),
            name: $finalName,
            domain: $finalSlug,
        );
    }

    public function createCommercialTenant(
        string $userId,
        string $name,
        string $slug,
        mixed $planType = null,
        mixed $isPro = null,
        mixed $productLimit = null,
        mixed $logoPath = null,
        mixed $categoryId = null,
        mixed $cityId = null,
        mixed $whatsapp = null,
        mixed $businessType = null,
        mixed $commercialMode = null,
        mixed $rubroKey = null,
        mixed $tenantId = null,
    ): TenantEntity {
        $mode = CommercialMode::safeFrom(is_string($commercialMode) ? $commercialMode : null);
        $derivedBusinessType = $mode->businessType();
        $tenant = TenantEntity::create(
            id: is_string($tenantId) && $tenantId !== '' ? $tenantId : (string) Str::orderedUuid(),
            name: $name,
            domain: $slug,
        );

        $tenant->configureCommercialProfile(
            planType: (string) ($planType ?? 'semilla'),
            isPro: (bool) $isPro,
            productLimit: (int) ($productLimit ?? 10),
            logoPath: is_string($logoPath) && $logoPath !== '' ? $logoPath : null,
            categoryId: is_numeric($categoryId) ? (int) $categoryId : null,
            cityId: is_numeric($cityId) ? (int) $cityId : null,
            whatsapp: is_string($whatsapp) && $whatsapp !== '' ? $whatsapp : null,
            businessType: $derivedBusinessType,
            commercialMode: $mode,
            rubroKey: is_string($rubroKey) && $rubroKey !== '' ? $rubroKey : null,
            ownerUserId: $userId,
        );

        return $tenant;
    }
}
