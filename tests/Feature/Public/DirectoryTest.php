<?php

declare(strict_types=1);

use App\Models\Tenant;

/**
 * Directory Route — Attribute Safety Tests
 *
 * INVARIANT: /directory and /explore must return 200 OK without
 * triggering MissingAttributeException on Tenant model.
 */

it('loads the directory without missing attribute exceptions', function () {
    // Ensure at least one active tenant exists for the query
    $tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->get('/directory');

    $response->assertStatus(200);
});

it('loads explore without missing attribute exceptions', function () {
    // Ensure at least one visible tenant with logo exists
    $tenant = Tenant::factory()->create([
        'is_visible' => true,
        'logo_path' => 'logos/test.png',
    ]);

    $response = $this->get('/explore');

    $response->assertStatus(200);
});
