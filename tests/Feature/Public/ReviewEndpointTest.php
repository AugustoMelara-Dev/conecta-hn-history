<?php

use App\Models\Tenant;
use App\Models\User;
use App\Http\Middleware\CheckMaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('submit review endpoint returns inertia compliant redirect', function () {
    $this->withoutMiddleware(CheckMaintenanceMode::class);

    $user = User::factory()->create();

    $tenantId = Str::uuid()->toString();
    $tenant = Tenant::forceCreate([
        'id' => $tenantId,
        'name' => 'Smoke Test Tenant',
        'slug' => 'smoke-test-tenant',
    ]);

    $response = $this->actingAs($user)
        ->from('/')
        ->post(route('reviews.store'), [
            'tenant_id' => $tenant->id,
            'rating' => 5,
            'comment' => 'Integration test review',
        ]);

    $response->assertStatus(303);
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('reviews', [
        'tenant_id' => $tenant->id,
        'rating' => 5,
        'is_approved' => false,
    ]);
});
