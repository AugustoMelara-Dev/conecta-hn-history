<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RoutingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function renders_landing_page_at_root(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));
    }

    #[Test]
    public function renders_marketplace_at_explore(): void
    {
        $response = $this->get('/explore');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Home'));
    }

    #[Test]
    public function routes_tenant_via_t_prefix(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'test-barbershop',
        ]);

        app(TenantContext::class)->reset();

        $response = $this->get('/t/test-barbershop');

        $response->assertStatus(200);
    }

    #[Test]
    public function does_not_expose_legacy_bare_slug_route(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'legacy-business',
        ]);

        $response = $this->get('/legacy-business');

        $response->assertStatus(404);
    }

    #[Test]
    public function returns_404_for_non_existent_tenant(): void
    {
        app(TenantContext::class)->reset();

        $response = $this->get('/t/non-existent-tenant');

        $response->assertStatus(404);
    }

    #[Test]
    public function does_not_conflict_with_login(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    #[Test]
    public function does_not_conflict_with_register(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    #[Test]
    public function does_not_conflict_with_directorio(): void
    {
        $response = $this->get('/directorio');

        $response->assertStatus(200);
    }

    #[Test]
    public function does_not_conflict_with_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function generates_correct_url_for_home_route(): void
    {
        self::assertSame('/', route('home', [], false));
    }

    #[Test]
    public function generates_correct_url_for_explore_route(): void
    {
        self::assertStringContainsString('/explore', route('public.explore'));
    }

    #[Test]
    public function generates_correct_url_for_tenant_microsite_route(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'my-business',
        ]);

        self::assertStringContainsString('/t/my-business', route('tenant.microsite.show', $tenant->slug));
    }
}
