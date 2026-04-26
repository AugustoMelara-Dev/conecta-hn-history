<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Models\Tenant;

interface RubroManifestReaderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function forTenant(Tenant $tenant): array;

    /**
     * @return array<string, mixed>
     */
    public function forBusinessType(string $businessType): array;

    /**
     * @return array<string, mixed>
     */
    public function get(string $rubroKey): array;

    /**
     * @return array<int, string>
     */
    public function capabilitiesForTenant(Tenant $tenant): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function registrationOptions(): array;

    /**
     * @return array<string, string>
     */
    public function categorySuggestions(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function segmentProfiles(): array;

    /**
     * @return array<string, array{capabilities: list<string>}>
     */
    public function featureCapabilityMap(): array;

    /**
     * @return array<string, mixed>
     */
    public function onboardingForTenant(Tenant $tenant): array;

    /**
     * @return array<string, mixed>
     */
    public function dynamicFormsForTenant(Tenant $tenant): array;

    /**
     * @return list<string>
     */
    public function allowedBusinessTypes(): array;

    public function defaultBusinessType(): string;

    public function hasModule(Tenant $tenant, string $module, string $surface = 'business_app'): bool;

    public function version(): string;
}
