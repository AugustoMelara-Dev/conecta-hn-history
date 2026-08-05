<?php

declare(strict_types=1);

use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Http\Resources\TenantCardResource;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\Support\GlobalMarketFixture;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.domain' => 'localhost']);
});

describe('Canonical public routing', function () {
    it('renders the SaaS landing at root with the canonical configurable pro price', function () {
        config()->set('subscriptions.plans.pro.price', 63900);

        $response = get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('proPlanPrice', 639));
    });

    it('renders the canonical buyer directory at /directorio', function () {
        $response = get('/directorio');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Directory/Index'));
    });

    it('keeps legacy discovery paths as redirects only', function () {
        get('/explore')
            ->assertRedirect('/directorio');

        get('/directory')
            ->assertStatus(301)
            ->assertRedirect('/directorio');
    });

    it('serves legal pages used by registration and footer links', function () {
        get('/legal/terms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Legal/Show')
                ->where('kind', 'terms')
            );

        get('/legal/privacy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Legal/Show')
                ->where('kind', 'privacy')
            );

        get('/terms')
            ->assertStatus(301)
            ->assertRedirect('/legal/terms');

        get('/privacy')
            ->assertStatus(301)
            ->assertRedirect('/legal/privacy');
    });

    it('serves app.localhost as the buyer directory alias', function () {
        get('http://app.localhost/')
            ->assertRedirect('/directorio');
    });

    it('serves tenant microsites on tenant subdomains', function () {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'test-barbershop',
            'is_active' => true,
        ]);

        get('http://test-barbershop.localhost/')
            ->assertStatus(200);
    });

    it('serves tenant microsites from the local bridge path', function () {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'test-barbershop',
            'is_active' => true,
        ]);

        get('/t/test-barbershop')
            ->assertStatus(200);
    });
});

describe('Public directory privacy', function () {
    it('does not expose tenant plan metadata in public cards', function () {
        $tenant = Tenant::factory()->make([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
        ]);

        $payload = (new TenantCardResource($tenant))->resolve(Request::create('/directorio'));

        expect(array_key_exists('is_pro', $payload))->toBeFalse();
        expect(array_key_exists('plan_type', $payload))->toBeFalse();
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

        get('/t/private-plan-shop')
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
        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'name' => 'Mercado Norte',
            'slug' => 'mercado-norte',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        GlobalMarketFixture::assignCanada($tenant);

        $productPayload = [
            'name' => 'Cafe molido premium',
            'price' => 120,
            'price_minor' => 12000,
            'currency' => 'CAD',
            'is_active' => true,
            'is_visible' => true,
        ];

        if (Schema::hasColumn('products', 'status')) {
            $productPayload['status'] = 'active';
        }

        Product::factory()->forTenant($tenant)->create($productPayload);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        postJson('/api/v1/directory/guide/chat', [
            'message' => 'que puedo comprar con 150 lempiras',
        ])
            ->assertOk()
            ->assertJsonPath('meta.privacy_scope', 'public_directory_only')
            ->assertJsonPath('items.0.business_name', 'Mercado Norte')
            ->assertJsonPath('items.0.business_url', '/t/mercado-norte')
            ->assertJsonPath('items.0.url', '/t/mercado-norte')
            ->assertJsonMissingPath('items.0.tenant');
    });
});

describe('Compatibility and named routes', function () {
    it('keeps /register as a business-registration bridge', function () {
        get('/register')
            ->assertRedirect('/registrar-negocio');
    });

    it('does not conflict with /login', function () {
        $response = get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    });

    it('keeps /dashboard protected', function () {
        get('/dashboard')
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
