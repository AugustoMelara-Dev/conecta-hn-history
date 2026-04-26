<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Services\DynamicForms\ModuleAdapters\ConfigDynamicModuleAdapter;
use App\Services\DynamicForms\ModuleAdapters\DynamicModuleAdapterInterface;

final class DynamicModuleAdapterRegistry
{
    public function __construct(
        private readonly DynamicRuntimeFeatureManager $featureManager,
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
