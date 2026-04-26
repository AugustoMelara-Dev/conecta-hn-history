<?php

declare(strict_types=1);

namespace App\Services\DynamicForms\ModuleAdapters;

final class ConfigDynamicModuleAdapter implements DynamicModuleAdapterInterface
{
    /**
     * @param  array<int, string>  $legacyResources
     * @param  array<int, string>  $dynamicEntities
     */
    public function __construct(
        private readonly string $moduleKey,
        private readonly array $legacyResources,
        private readonly array $dynamicEntities,
        private readonly bool $migrationEnabled,
    ) {}

    public function moduleKey(): string
    {
        return $this->moduleKey;
    }

    public function legacyResources(): array
    {
        return $this->legacyResources;
    }

    public function dynamicEntities(): array
    {
        return $this->dynamicEntities;
    }

    public function isMigrationEnabled(): bool
    {
        return $this->migrationEnabled;
    }
}
