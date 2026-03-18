<?php

declare(strict_types=1);

/**
 * Routing Architecture Tests
 *
 * Verifies the "Luxury Entrance" routing strategy:
 * - / → B2B Landing Page (Welcome.jsx)
 * - /explore → Marketplace Discovery (Public/Home.jsx)
 * - /@{slug} → Tenant Microsite (safe namespace, no collisions)
 * - /{slug} → Legacy redirect to /@{slug}
 */

use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Luxury Entrance Routing', function () {
    it('renders landing page at root', function () {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Welcome'));
    });

    it('renders marketplace at /explore', function () {
        $response = $this->get('/explore');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Public/Home'));
    });

    it('routes tenant via /t prefix', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'test-barbershop',
        ]);

        app(TenantContext::class)->reset();

        $response = $this->get('/t/test-barbershop');

        $response->assertStatus(200);
    });

    it('does not expose legacy bare slug route', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'legacy-business',
        ]);

        $response = $this->get('/legacy-business');

        $response->assertStatus(404);
    });

    it('returns 404 for non-existent tenant', function () {
        app(TenantContext::class)->reset();

        $response = $this->get('/t/non-existent-tenant');

        $response->assertStatus(404);
    });
});

describe('System Routes Protection', function () {
    it('does not conflict with /login', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    });

    it('does not conflict with /register', function () {
        $response = $this->get('/register');

        $response->assertStatus(200);
    });

    it('does not conflict with /directorio', function () {
        $response = $this->get('/directorio');

        $response->assertStatus(200);
    });

    it('does not conflict with /dashboard (requires auth)', function () {
        $response = $this->get('/dashboard');

        // Should redirect to login since unauthenticated
        $response->assertRedirect('/login');
    });
});

describe('Named Routes', function () {
    it('generates correct URL for home route', function () {
        expect(route('home', [], false))->toBe('/');
    });

    it('generates correct URL for explore route', function () {
        expect(route('public.explore'))->toContain('/explore');
    });

    it('generates correct URL for tenant microsite route', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'my-business',
        ]);

        expect(route('tenant.microsite.show', $tenant->slug))->toContain('/t/my-business');
    });
});
