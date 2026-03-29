<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BusinessRolePresetCatalog
{
    public function __construct(
        private readonly BusinessSegmentResolver $segmentResolver,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forTenant(Tenant $tenant): array
    {
        $segmentKey = $this->segmentResolver->resolveForTenant($tenant);
        $businessType = $tenant->business_type->value;

        return collect(config('rbac.presets', []))
            ->filter(fn (mixed $preset): bool => is_array($preset))
            ->filter(fn (array $preset): bool => $this->matchesTenant($tenant, $preset, $businessType, $segmentKey))
            ->mapWithKeys(function (array $preset, string $key): array {
                return [$key => $this->normalizePreset($key, $preset)];
            })
            ->sortBy([
                static fn (array $preset): int => (int) ($preset['priority'] ?? 999),
                static fn (array $preset): string => (string) ($preset['label'] ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForTenant(Tenant $tenant, ?string $presetKey): ?array
    {
        if (! is_string($presetKey) || trim($presetKey) === '') {
            return null;
        }

        return $this->forTenant($tenant)[$presetKey] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function permissionsForTenantPreset(Tenant $tenant, ?string $presetKey): array
    {
        $preset = $this->findForTenant($tenant, $presetKey);

        if (! is_array($preset)) {
            return [];
        }

        /** @var Collection<int, string> $permissions */
        $permissions = collect((array) ($preset['permissions'] ?? []))
            ->filter(static fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->unique()
            ->values();

        return $permissions->all();
    }

    /**
     * @param  array<string, mixed>  $preset
     * @return array<string, mixed>
     */
    private function normalizePreset(string $key, array $preset): array
    {
        $label = (string) ($preset['label'] ?? Str::of($key)->replace('_', ' ')->title()->value());
        $description = (string) ($preset['description'] ?? 'Perfil operativo recomendado para este rubro.');

        return array_merge($preset, [
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ]);
    }

    /**
     * @param  array<string, mixed>  $preset
     */
    private function matchesTenant(Tenant $tenant, array $preset, string $businessType, string $segmentKey): bool
    {
        $presetBusinessTypes = array_values(array_filter(
            (array) ($preset['business_types'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        ));

        if ($presetBusinessTypes !== [] && ! in_array($businessType, $presetBusinessTypes, true)) {
            return false;
        }

        $presetSegments = array_values(array_filter(
            (array) ($preset['segments'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        ));

        if ($presetSegments !== [] && ! in_array($segmentKey, $presetSegments, true)) {
            return false;
        }

        $requiredCapabilities = $this->resolveRequiredCapabilities($preset);

        foreach ($requiredCapabilities as $requiredCapability) {
            $capability = Capability::tryFrom($requiredCapability);

            if (! $capability instanceof Capability || ! $tenant->hasCapability($capability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $preset
     * @return array<int, string>
     */
    private function resolveRequiredCapabilities(array $preset): array
    {
        $capabilities = (array) ($preset['requires_capabilities'] ?? []);

        if (isset($preset['requires_capability']) && is_string($preset['requires_capability'])) {
            $capabilities[] = $preset['requires_capability'];
        }

        return collect($capabilities)
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
