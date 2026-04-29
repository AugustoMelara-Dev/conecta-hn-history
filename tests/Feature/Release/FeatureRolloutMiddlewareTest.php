<?php

declare(strict_types=1);

namespace Tests\Feature\Release;

use App\Models\Tenant;
use App\Settings\BaseSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class FeatureRolloutMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('feature.rollout:new_checkout')
            ->get('/_test/rollouts/{tenant:slug}', static fn (Tenant $tenant) => response('ok'));
    }

    public function test_it_blocks_tenant_outside_rollout(): void
    {
        $this->storeRollouts([
            [
                'key' => 'new_checkout',
                'enabled' => true,
                'tenant_slugs' => ['allowed-shop'],
            ],
        ]);

        $tenant = Tenant::factory()->create(['slug' => 'blocked-shop']);

        $this->get('/_test/rollouts/'.$tenant->slug)->assertNotFound();
    }

    public function test_it_allows_tenant_inside_rollout(): void
    {
        $this->storeRollouts([
            [
                'key' => 'new_checkout',
                'enabled' => true,
                'tenant_slugs' => ['allowed-shop'],
            ],
        ]);

        $tenant = Tenant::factory()->create(['slug' => 'allowed-shop']);

        $this->get('/_test/rollouts/'.$tenant->slug)
            ->assertOk()
            ->assertSee('ok');
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
}
