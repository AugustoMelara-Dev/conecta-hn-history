<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Release;

use App\Models\Tenant;
use App\Settings\BaseSettings;
use App\Support\Release\FeatureRolloutManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FeatureRolloutManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unknown_feature_is_disabled_by_default(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'beta-shop']);

        $this->assertFalse($this->manager()->enabled('missing_feature', $tenant));
    }

    public function test_disabled_flag_is_not_enabled_for_matching_tenant(): void
    {
        $this->storeRollouts([
            [
                'key' => 'new_checkout',
                'enabled' => false,
                'tenant_slugs' => ['beta-shop'],
            ],
        ]);

        $tenant = Tenant::factory()->create(['slug' => 'beta-shop']);

        $this->assertFalse($this->manager()->enabled('new_checkout', $tenant));
    }

    public function test_tenant_slug_allows_specific_tenant(): void
    {
        $this->storeRollouts([
            [
                'key' => 'new_checkout',
                'enabled' => true,
                'tenant_slugs' => ['beta-shop'],
            ],
        ]);

        $allowed = Tenant::factory()->create(['slug' => 'beta-shop']);
        $blocked = Tenant::factory()->create(['slug' => 'regular-shop']);

        $this->assertTrue($this->manager()->enabled('new_checkout', $allowed));
        $this->assertFalse($this->manager()->enabled('new_checkout', $blocked));
    }

    public function test_plan_allows_matching_tenant_plan(): void
    {
        $this->storeRollouts([
            [
                'key' => 'growth_center',
                'enabled' => true,
                'plans' => ['pro'],
            ],
        ]);

        $proTenant = Tenant::factory()->pro()->create(['slug' => 'pro-shop']);
        $semillaTenant = Tenant::factory()->create(['slug' => 'semilla-shop']);

        $this->assertTrue($this->manager()->enabled('growth_center', $proTenant));
        $this->assertFalse($this->manager()->enabled('growth_center', $semillaTenant));
    }

    public function test_business_type_allows_matching_tenant_type(): void
    {
        $this->storeRollouts([
            [
                'key' => 'booking_upgrade',
                'enabled' => true,
                'business_types' => ['service'],
            ],
        ]);

        $serviceTenant = Tenant::factory()->create(['business_type' => 'service']);
        $retailTenant = Tenant::factory()->create(['business_type' => 'retail']);

        $this->assertTrue($this->manager()->enabled('booking_upgrade', $serviceTenant));
        $this->assertFalse($this->manager()->enabled('booking_upgrade', $retailTenant));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rollouts
     */
    private function storeRollouts(array $rollouts): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'general', 'name' => 'feature_rollouts'],
            [
                'payload' => json_encode($rollouts),
                'locked' => false,
                'updated_at' => now(),
            ]
        );

        Cache::forget('settings.general');
        BaseSettings::clearRuntimeCache();
    }

    private function manager(): FeatureRolloutManager
    {
        return new FeatureRolloutManager;
    }
}
