<?php

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Settings\BaseSettings;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    app(TenantContext::class)->reset();
    Cache::forget('settings.general');

    $property = new ReflectionProperty(BaseSettings::class, 'runtimeCache');
    $property->setValue([]);
});

test('product policy enforces dynamic capacity limits from general settings', function () {
    DB::table('settings')->updateOrInsert(
        ['group' => 'general', 'name' => 'free_plan_product_limit'],
        [
            'payload' => json_encode(1),
            'locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    DB::table('settings')->updateOrInsert(
        ['group' => 'general', 'name' => 'pro_plan_product_limit'],
        [
            'payload' => json_encode(50),
            'locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    Cache::forget('settings.general');
    $property = new ReflectionProperty(BaseSettings::class, 'runtimeCache');
    $property->setValue([]);

    $settings = app(GeneralSettings::class);
    $settings->free_plan_product_limit = 1;
    $settings->pro_plan_product_limit = 50;
    app()->instance(GeneralSettings::class, $settings);

    $tenantId = Str::uuid()->toString();
    $tenant = Tenant::forceCreate([
        'id' => $tenantId,
        'name' => 'Capacity Test Tenant',
        'slug' => 'capacity-test-'.Str::random(6),
        'plan_type' => 'semilla',
        'subscription_status' => 'active',
    ]);

    app(TenantContext::class)->setTenant($tenant->id);

    DB::table('products')->insert([
        'tenant_id' => $tenantId,
        'name' => 'Mock Product',
        'slug' => 'mock-product',
        'price' => 100.00,
        'is_active' => true,
        'is_visible' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Filament::shouldReceive('getTenant')->andReturn($tenant);

    $user = User::factory()->create();
    $guard = Mockery::mock(TenantPermissionGuardInterface::class);
    $guard->shouldReceive('allows')
        ->with($user, $tenant, Capability::CATALOG_BASIC, Permission::CATALOG_CREATE)
        ->andReturn(true);
    app()->instance(TenantPermissionGuardInterface::class, $guard);

    $policy = new ProductPolicy();

    $response = $policy->create($user);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toContain('limite de 1 productos')
        ->and($response->message())->toContain('Plan Gratuito');
});
