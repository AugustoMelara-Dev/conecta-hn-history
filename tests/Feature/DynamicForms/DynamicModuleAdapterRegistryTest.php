<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Services\DynamicForms\DynamicModuleAdapterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DynamicModuleAdapterRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_config_driven_adapter_for_module(): void
    {
        config()->set('dynamic-runtime.enabled', true);
        config()->set('dynamic-runtime.kill_switch', false);
        config()->set('dynamic-runtime.modules.crm', true);
        config()->set('dynamic-runtime.module_adapters.crm', [
            'legacy_resources' => ['leads'],
            'dynamic_entities' => ['lead'],
            'migration_enabled' => true,
        ]);

        $adapter = app(DynamicModuleAdapterRegistry::class)->forModule('crm');

        $this->assertSame('crm', $adapter->moduleKey());
        $this->assertSame(['leads'], $adapter->legacyResources());
        $this->assertSame(['lead'], $adapter->dynamicEntities());
        $this->assertTrue($adapter->isMigrationEnabled());
    }

    public function test_kill_switch_disables_all_active_module_adapters(): void
    {
        config()->set('dynamic-runtime.enabled', true);
        config()->set('dynamic-runtime.kill_switch', true);
        config()->set('dynamic-runtime.modules.crm', true);

        $adapters = app(DynamicModuleAdapterRegistry::class)->activeAdapters();

        $this->assertSame([], $adapters);
    }
}
