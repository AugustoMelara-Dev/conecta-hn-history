<?php

declare(strict_types=1);

use App\Domain\Shared\Services\TenantContext;
use App\Models\CrmPipeline;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(TenantContext::class)->reset();
});

it('injects the active tenant when creating tenant-owned models', function (): void {
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->setTenant((string) $tenant->id);

    $pipeline = CrmPipeline::query()->create([
        'name' => 'Ventas',
        'key' => 'ventas',
    ]);

    expect((string) $pipeline->tenant_id)->toBe((string) $tenant->id);
});

it('blocks force-filled tenant hopping during creation', function (): void {
    $activeTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    app(TenantContext::class)->setTenant((string) $activeTenant->id);

    $pipeline = new CrmPipeline([
        'name' => 'Cross Tenant',
        'key' => 'cross-tenant',
    ]);

    $pipeline->forceFill(['tenant_id' => $otherTenant->id]);

    expect(fn () => $pipeline->save())
        ->toThrow(LogicException::class, 'Tenant-owned models cannot be created for a different tenant than the active context.');
});

it('allows explicit tenant ownership in root context for seeders and jobs', function (): void {
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->setAsSystemRoot();

    $pipeline = CrmPipeline::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Seeder Pipeline',
        'key' => 'seeder-pipeline',
    ]);

    expect((string) $pipeline->tenant_id)->toBe((string) $tenant->id);
});
