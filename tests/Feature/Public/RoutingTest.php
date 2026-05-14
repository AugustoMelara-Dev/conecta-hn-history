<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

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

    it('serves legal pages used by registration and footer links', function () {
        $this->get('/legal/terms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Legal/Show')
                ->where('kind', 'terms')
            );

        $this->get('/legal/privacy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Legal/Show')
                ->where('kind', 'privacy')
            );

        $this->get('/terms')
            ->assertStatus(301)
            ->assertRedirect('/legal/terms');

        $this->get('/privacy')
            ->assertStatus(301)
            ->assertRedirect('/legal/privacy');
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

    it('serves tenant microsites from the local bridge path', function () {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'test-barbershop',
            'is_active' => true,
        ]);

        $this->get('/t/test-barbershop')
            ->assertStatus(200);
    });
});

describe('Public directory privacy', function () {
    it('does not expose tenant plan metadata in public cards', function () {
        $tenant = Tenant::factory()->make([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
        ]);

        $payload = (new \App\Http\Resources\TenantCardResource($tenant))->resolve(Request::create('/directorio'));

        expect($payload)
            ->not->toHaveKey('is_pro')
            ->not->toHaveKey('plan_type');
    });

    it('does not expose plan metadata in public microsite props', function () {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'private-plan-shop',
            'is_active' => true,
            'plan_type' => 'pro',
            'is_pro' => true,
        ]);

        $this->get('/t/private-plan-shop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Show')
                ->missing('tenantPlan')
                ->missing('productLimit')
                ->missing('tenant.plan')
                ->missing('tenant.is_pro')
            );
    });

    it('answers directory guide questions with public catalog data only', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'name' => 'Mercado Norte',
            'slug' => 'mercado-norte',
            'is_active' => true,
        ]);

        $productPayload = [
            'name' => 'Cafe molido premium',
            'price' => 120,
            'is_active' => true,
            'is_visible' => true,
        ];

        if (Schema::hasColumn('products', 'status')) {
            $productPayload['status'] = 'active';
        }

        Product::factory()->forTenant($tenant)->create($productPayload);

        $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'que puedo comprar con 150 lempiras',
        ])
            ->assertOk()
            ->assertJsonPath('meta.privacy_scope', 'public_directory_only')
            ->assertJsonPath('items.0.tenant.slug', 'mercado-norte')
            ->assertJsonPath('items.0.url', '/t/mercado-norte')
            ->assertJsonMissingPath('items.0.tenant.plan_type');
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
        expect(route('home'))->toBe(config('app.url'))
            ->and(route('directory.index'))->toEndWith('/directorio')
            ->and(route('public.explore'))->toEndWith('/explore');
    });

    it('generates tenant microsite URLs on the subdomain route', function () {
        expect(route('tenant.microsite.show', ['tenant' => 'my-business']))
            ->toContain('my-business.localhost');
    });
});
