<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.domain' => 'localhost']);
});

describe('Canonical public routing', function () {
    it('renders the SaaS landing at root', function () {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Welcome'));
    });

    it('renders the canonical buyer directory at /directorio', function () {
        $response = $this->get('/directorio');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Directory/Index'));
    });

    it('keeps legacy discovery paths as redirects only', function () {
        $this->get('/explore')
            ->assertRedirect('/directorio');

        $this->get('/directory')
            ->assertStatus(301)
            ->assertRedirect('/directorio');
    });

    it('serves app.localhost as the buyer directory alias', function () {
        $this->get('http://app.localhost/')
            ->assertRedirect('/directorio');
    });

    it('serves tenant microsites on tenant subdomains', function () {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'test-barbershop',
            'is_active' => true,
        ]);

        $this->get('http://test-barbershop.localhost/')
            ->assertStatus(200);
    });
});

describe('Compatibility and named routes', function () {
    it('keeps /register as a business-registration bridge', function () {
        $this->get('/register')
            ->assertRedirect('/registrar-negocio');
    });

    it('does not conflict with /login', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    });

    it('keeps /dashboard protected', function () {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    });

    it('generates route names for the canonical surfaces', function () {
        expect(route('home'))->toBe('http://localhost')
            ->and(route('directory.index'))->toEndWith('/directorio')
            ->and(route('public.explore'))->toEndWith('/explore');
    });

    it('generates tenant microsite URLs on the subdomain route', function () {
        expect(route('tenant.microsite.show', ['tenant' => 'my-business']))
            ->toContain('my-business.localhost');
    });
});
