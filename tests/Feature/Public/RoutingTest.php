<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

final class RoutingTest extends TestCase
{
    use DatabaseTransactions;

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
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Explore'));
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
    public function tenant_microsite_exposes_business_type_aware_props(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'legal-studio-hn',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

        Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta legal',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana López',
        ]);

        app(TenantContext::class)->reset();

        $response = $this->get('/t/legal-studio-hn');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tenant/Show')
            ->where('tenant.business_type', 'service')
            ->where('tenant.storefront_profile.label', 'Servicios y citas')
            ->where(
                'tenant.storefront_profile.primary_cta_label',
                static fn (string $label): bool => in_array($label, ['Reservar ahora', 'Reservar cita'], true)
            )
            ->has('tenant.services', 1)
        );
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
    public function returns_404_for_unpublished_tenant_instead_of_throwing_a_500(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'inactive-storefront',
            'status' => 'approved',
            'subscription_status' => 'inactive',
        ]);

        app(TenantContext::class)->reset();

        $response = $this->get('/t/inactive-storefront');

        $response->assertStatus(404);
    }

    #[Test]
    public function returns_404_for_provisional_tenant_for_public_visitors(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'tenant-provisional',
            'status' => 'approved',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
        ]);

        app(TenantContext::class)->reset();

        $response = $this->get('/t/tenant-provisional');

        $response->assertStatus(404);
    }

    #[Test]
    public function returns_404_for_paused_tenant_for_public_visitors(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'tenant-paused',
            'status' => 'approved',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => now(),
            'publication_pause_reason' => 'Disputa abierta',
        ]);

        app(TenantContext::class)->reset();

        $response = $this->get('/t/tenant-paused');

        $response->assertStatus(404);
    }

    #[Test]
    public function allows_the_owner_to_preview_an_unpublished_tenant(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'owner-preview-storefront',
            'status' => 'approved',
            'subscription_status' => 'inactive',
        ]);

        app(TenantContext::class)->reset();

        $response = $this
            ->actingAs($user)
            ->get('/t/owner-preview-storefront');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tenant/Show')
            ->where('previewMode', true)
            ->where('previewReason', 'La suscripcion actual no permite visibilidad publica.')
        );
    }

    #[Test]
    public function owner_preview_reason_mentions_claim_verification_when_tenant_is_provisional(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'preview-provisional-tenant',
            'status' => 'approved',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
        ]);

        app(TenantContext::class)->reset();

        $response = $this
            ->actingAs($user)
            ->get('/t/preview-provisional-tenant');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tenant/Show')
            ->where('previewMode', true)
            ->where('previewReason', 'Tu negocio aun no completa la verificacion de titularidad.')
        );
    }

    #[Test]
    public function does_not_conflict_with_login(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    #[Test]
    public function redirects_authenticated_unverified_users_to_the_public_verification_notice_instead_of_filament(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/login');

        $response->assertRedirect(route('verification.notice', [], false));
    }

    #[Test]
    public function does_not_conflict_with_register(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('tenant.register', [], false));
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
    public function serves_manifest_with_the_correct_content_type(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');
        self::assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        self::assertSame('manifest.webmanifest', $response->baseResponse->getFile()->getFilename());
    }

    #[Test]
    public function serves_favicon_assets_without_hitting_the_landing_page(): void
    {
        $svgResponse = $this->get('/favicon.svg');
        $pngResponse = $this->get('/icon-192x192.png');

        $svgResponse->assertOk();
        $svgResponse->assertHeader('Content-Type', 'image/svg+xml');
        $svgResponse->assertDontSee('component&quot;:&quot;Welcome&quot;', false);

        $pngResponse->assertOk();
        $pngResponse->assertHeader('Content-Type', 'image/png');
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
