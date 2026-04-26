<?php

declare(strict_types=1);

namespace App\Services\DynamicForms\ModuleAdapters;

interface DynamicModuleAdapterInterface
{
    public function moduleKey(): string;

    /**
     * @return array<int, string>
     */
    public function legacyResources(): array;

    /**
     * @return array<int, string>
     */
    public function dynamicEntities(): array;

    public function isMigrationEnabled(): bool;
}
