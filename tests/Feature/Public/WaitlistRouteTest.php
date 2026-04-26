<?php

use App\Http\Controllers\Public\WaitlistController;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Waitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('waitlist controller compiles and public route is registered', function () {
    expect(class_exists(WaitlistController::class))->toBeTrue();

    $routes = collect(Route::getRoutes()->getRoutesByName());

    expect($routes->has('public.booking.waitlist.store'))->toBeTrue();
});

test('waitlist rejects services from another tenant', function () {
    $tenantA = Tenant::factory()->pro()->create([
        'slug' => 'waitlist-a',
        'business_type' => 'service',
    ]);
    $tenantB = Tenant::factory()->pro()->create([
        'slug' => 'waitlist-b',
        'business_type' => 'service',
    ]);
    $foreignService = Service::factory()->create([
        'tenant_id' => $tenantB->id,
    ]);

    $this->from("/t/{$tenantA->slug}/book")
        ->post(route('public.booking.waitlist.store', ['tenant' => $tenantA->slug], false), [
            'service_id' => $foreignService->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'customer_name' => 'Cliente Espera',
            'customer_email' => 'espera@example.test',
            'customer_phone' => '9999-1111',
        ])
        ->assertRedirect("/t/{$tenantA->slug}/book")
        ->assertSessionHasErrors('service_id');

    expect(Waitlist::query()->withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count())->toBe(0);
});

test('waitlist creates a tenant scoped entry for a valid service', function () {
    $tenant = Tenant::factory()->pro()->create([
        'slug' => 'waitlist-valid',
        'business_type' => 'service',
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $this->from("/t/{$tenant->slug}/book")
        ->post(route('public.booking.waitlist.store', ['tenant' => $tenant->slug], false), [
            'service_id' => $service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'customer_name' => 'Cliente Espera',
            'customer_email' => 'espera@example.test',
            'customer_phone' => '9999-1111',
        ])
        ->assertRedirect("/t/{$tenant->slug}/book")
        ->assertSessionHas('success');

    app(TenantContext::class)->setTenant($tenant->id);

    expect(Waitlist::query()
        ->where('tenant_id', $tenant->id)
        ->where('service_id', $service->id)
        ->where('customer_email', 'espera@example.test')
        ->exists())->toBeTrue();
});
