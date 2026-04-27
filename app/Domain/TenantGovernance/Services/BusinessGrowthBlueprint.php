<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Models\Tenant;

final class BusinessGrowthBlueprint
{
    public function __construct(
        private readonly BusinessSegmentResolver $segmentResolver,
        private readonly RubroManifestReaderInterface $rubroManifest,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forTenant(Tenant $tenant): array
    {
        $businessType = $tenant->business_type->value;
        $segmentKey = $this->segmentResolver->resolveForTenant($tenant);
        $legacy = $this->forSegment($segmentKey, $businessType);
        $manifest = $this->rubroManifest->growthProfileForTenant($tenant);

        return array_replace_recursive($legacy, $manifest);
    }

    /**
     * @return array<string, mixed>
     */
    public function forSegment(string $segmentKey, string $businessType): array
    {
        /** @var array<string, string> $defaults */
        $defaults = config('business-growth.defaults', []);
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = config('business-growth.profiles', []);

        $fallbackKey = $defaults[$businessType] ?? 'retail_growth';
        $fallbackProfile = $profiles[$fallbackKey] ?? [];

        if (isset($profiles[$segmentKey])) {
            return array_replace_recursive($fallbackProfile, $profiles[$segmentKey]);
        }

        return $fallbackProfile;
    }
}
