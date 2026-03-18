<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Factories;

use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\TenantPlan;

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
            : self::slugify($finalName);

        $tenant = TenantEntity::create(
            id: \Ramsey\Uuid\Uuid::uuid7()->toString(),
            name: $finalName,
            domain: $finalSlug
        );
        $tenant->upgradeToPlan(TenantPlan::SEMILLA->value);

        return $tenant;
    }

    public function createCommercialTenant(
        string $name,
        string $slug,
        string $ownerId,
        TenantPlan $planType,
        bool $isPro = false,
        int $productLimit = 10,
        ?string $logoPath = null,
        ?int $categoryId = null,
        ?int $cityId = null,
        ?string $whatsapp = null,
    ): TenantEntity {
        $tenant = TenantEntity::create(
            id: \Ramsey\Uuid\Uuid::uuid7()->toString(),
            name: $name,
            domain: $slug
        );

        $tenant->setupProfile(
            ownerId: $ownerId,
            categoryId: $categoryId,
            cityId: $cityId,
            logoPath: $logoPath,
            whatsapp: $whatsapp,
            isPro: $isPro,
            productLimit: $productLimit
        );

        $tenant->upgradeToPlan($planType->value);

        return $tenant;
    }

    /**
     * Generate a URL-safe slug from a string without framework helpers.
     * Equivalent to Laravel's Str::slug($value, '-').
     */
    private static function slugify(string $value): string
    {
        // Transliterate to ASCII
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value) ?? strtolower($value);
        // Replace non-alphanumeric with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Trim leading/trailing hyphens
        return trim($slug, '-');
    }
}
