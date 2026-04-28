<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Models\Tenant;
use App\Services\DynamicForms\DynamicModuleAdapterRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class DynamicModuleAdapterRegistryTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_tenant_adapter_merges_rubro_manifest_dynamic_entities(): void
    {
        config()->set('dynamic-runtime.enabled', true);
        config()->set('dynamic-runtime.kill_switch', false);
        config()->set('dynamic-runtime.modules.crm', true);
        config()->set('dynamic-runtime.module_adapters.crm', [
            'legacy_resources' => ['leads'],
            'dynamic_entities' => ['lead'],
            'migration_enabled' => true,
        ]);

        $tenant = Tenant::factory()->create([
            'business_type' => 'service',
            'rubro_key' => 'service_general',
        ]);

        $adapter = app(DynamicModuleAdapterRegistry::class)->forTenantModule($tenant, 'crm');

        $this->assertSame('crm', $adapter->moduleKey());
        $this->assertSame(['leads'], $adapter->legacyResources());
        $this->assertSame(['lead', 'client_discovery'], $adapter->dynamicEntities());
        $this->assertTrue($adapter->isMigrationEnabled());
    }
}
