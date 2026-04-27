<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Models\Tenant;
use App\Services\DynamicForms\ModuleAdapters\ConfigDynamicModuleAdapter;
use App\Services\DynamicForms\ModuleAdapters\DynamicModuleAdapterInterface;

final class DynamicModuleAdapterRegistry
{
    public function __construct(
        private readonly DynamicRuntimeFeatureManager $featureManager,
        private readonly RubroManifestReaderInterface $rubroManifest,
    ) {}

    public function forModule(string $moduleKey): DynamicModuleAdapterInterface
    {
        $config = (array) config("dynamic-runtime.module_adapters.{$moduleKey}", []);

        return new ConfigDynamicModuleAdapter(
            moduleKey: $moduleKey,
            legacyResources: array_values(array_filter((array) ($config['legacy_resources'] ?? []), 'is_string')),
            dynamicEntities: array_values(array_filter((array) ($config['dynamic_entities'] ?? []), 'is_string')),
            migrationEnabled: $this->featureManager->isModuleMigrationEnabled($moduleKey),
        );
    }

    public function forTenantModule(Tenant $tenant, string $moduleKey): DynamicModuleAdapterInterface
    {
        $adapter = $this->forModule($moduleKey);
        $manifestEntities = collect((array) data_get($this->rubroManifest->dynamicFormsForTenant($tenant), 'entities', []))
            ->filter(static fn (mixed $definition): bool => is_array($definition) && (string) ($definition['module'] ?? '') === $moduleKey)
            ->map(static fn (array $definition): string => (string) ($definition['entity_key'] ?? ''))
            ->filter(static fn (string $entityKey): bool => $entityKey !== '')
            ->values()
            ->all();

        if ($manifestEntities === []) {
            return $adapter;
        }

        return new ConfigDynamicModuleAdapter(
            moduleKey: $moduleKey,
            legacyResources: $adapter->legacyResources(),
            dynamicEntities: array_values(array_unique(array_merge($adapter->dynamicEntities(), $manifestEntities))),
            migrationEnabled: $adapter->isMigrationEnabled(),
        );
    }

    /**
     * @return array<int, DynamicModuleAdapterInterface>
     */
    public function activeAdapters(): array
    {
        $configured = array_keys((array) config('dynamic-runtime.module_adapters', []));
        $adapters = [];

        foreach ($configured as $moduleKey) {
            if (! is_string($moduleKey) || $moduleKey === '') {
                continue;
            }

            if (! $this->featureManager->isModuleEnabled($moduleKey)) {
                continue;
            }

            $adapters[] = $this->forModule($moduleKey);
        }

        return $adapters;
    }
}
