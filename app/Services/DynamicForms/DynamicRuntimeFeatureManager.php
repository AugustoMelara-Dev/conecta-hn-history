<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

final class DynamicRuntimeFeatureManager
{
    public function isKillSwitchActive(): bool
    {
        return (bool) config('dynamic-runtime.kill_switch', false);
    }

    public function isEnabled(): bool
    {
        return ! $this->isKillSwitchActive() && (bool) config('dynamic-runtime.enabled', true);
    }

    public function isAdminConfiguratorEnabled(): bool
    {
        return $this->isEnabled() && (bool) config('dynamic-runtime.admin_configurator_enabled', true);
    }

    public function isTenantConfiguratorEnabled(): bool
    {
        return $this->isEnabled() && (bool) config('dynamic-runtime.tenant_configurator_enabled', true);
    }

    public function isRecordRuntimeEnabled(): bool
    {
        return $this->isEnabled() && (bool) config('dynamic-runtime.record_runtime_enabled', true);
    }

    public function isPrintRuntimeEnabled(): bool
    {
        return $this->isEnabled() && (bool) config('dynamic-runtime.print_runtime_enabled', true);
    }

    public function isModuleEnabled(?string $moduleKey): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($moduleKey === null || trim($moduleKey) === '') {
            return true;
        }

        return (bool) config("dynamic-runtime.modules.{$moduleKey}", true);
    }

    public function isModuleMigrationEnabled(string $moduleKey): bool
    {
        if (! $this->isModuleEnabled($moduleKey)) {
            return false;
        }

        return (bool) config("dynamic-runtime.module_adapters.{$moduleKey}.migration_enabled", false);
    }
}
