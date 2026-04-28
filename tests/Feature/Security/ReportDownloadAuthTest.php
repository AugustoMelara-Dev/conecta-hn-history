<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('signed report downloads still require an authenticated user', function () {
    Storage::fake('local');
    $owner = User::factory()->tenantOwner()->create();
    $tenant = Tenant::factory()->forUser($owner)->create();
    Storage::disk('local')->put("tenants/{$tenant->id}/reports/sales.pdf", 'fake-pdf');

    $url = URL::temporarySignedRoute(
        'reports.download',
        now()->addMinutes(5),
        [
            'path' => "tenants/{$tenant->id}/reports/sales.pdf",
            'tenant' => (string) $tenant->id,
            'user' => (string) $owner->id,
        ]
    );

    $this->getJson($url)->assertUnauthorized();

    $response = $this->actingAs($owner, 'sanctum')
        ->get($url)
        ->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-cache')
        ->toContain('no-store');
});

test('signed report downloads reject another authenticated user', function () {
    Storage::fake('local');
    $owner = User::factory()->tenantOwner()->create();
    $tenant = Tenant::factory()->forUser($owner)->create();
    $intruder = User::factory()->create();
    Storage::disk('local')->put("tenants/{$tenant->id}/reports/sales.pdf", 'fake-pdf');

    $url = URL::temporarySignedRoute(
        'reports.download',
        now()->addMinutes(5),
        [
            'path' => "tenants/{$tenant->id}/reports/sales.pdf",
            'tenant' => (string) $tenant->id,
            'user' => (string) $owner->id,
        ]
    );

    $this->actingAs($intruder, 'sanctum')
        ->getJson($url)
        ->assertForbidden();
});

test('signed report downloads reject tenant path mismatch', function () {
    Storage::fake('local');
    $owner = User::factory()->tenantOwner()->create();
    $tenant = Tenant::factory()->forUser($owner)->create();
    $otherTenant = Tenant::factory()->create();
    Storage::disk('local')->put("tenants/{$otherTenant->id}/reports/sales.pdf", 'fake-pdf');

    $url = URL::temporarySignedRoute(
        'reports.download',
        now()->addMinutes(5),
        [
            'path' => "tenants/{$otherTenant->id}/reports/sales.pdf",
            'tenant' => (string) $tenant->id,
            'user' => (string) $owner->id,
        ]
    );

    $this->actingAs($owner, 'sanctum')
        ->getJson($url)
        ->assertForbidden();
});
