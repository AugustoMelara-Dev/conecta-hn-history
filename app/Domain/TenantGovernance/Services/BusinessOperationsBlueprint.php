<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Tenant;

final class BusinessOperationsBlueprint
{
    public function __construct(
        private readonly BusinessSegmentResolver $segmentResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forTenant(Tenant $tenant): array
    {
        $businessType = $tenant->business_type?->value ?? BusinessType::RETAIL->value;
        $segmentKey = $this->segmentResolver->resolveForTenant($tenant);

        return $this->forSegment($segmentKey, $businessType);
    }

    /**
     * @return array<string, mixed>
     */
    public function forSegment(string $segmentKey, string $businessType): array
    {
        /** @var array<string, string> $defaults */
        $defaults = config('business-operations.defaults', []);
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = config('business-operations.profiles', []);

        $fallbackKey = $defaults[$businessType] ?? 'retail_growth';
        $fallbackProfile = $profiles[$fallbackKey] ?? [];

        if (isset($profiles[$segmentKey]) && is_array($profiles[$segmentKey])) {
            return array_replace_recursive($fallbackProfile, $profiles[$segmentKey]);
        }

        return $fallbackProfile;
    }
}
