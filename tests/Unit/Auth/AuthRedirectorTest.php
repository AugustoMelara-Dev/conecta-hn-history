<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

final class AuthRedirectorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_is_redirected_to_directory_surface(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $destination = AuthRedirector::destinationFor($customer);

        $this->assertSame(
            'https://www.vitobusinessos.com'.route('directory.index', [], false),
            $destination
        );
    }

    public function test_guest_fallback_is_directory_surface_instead_of_legacy_dashboard(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
        ]);

        $destination = AuthRedirector::destinationFor(null);

        $this->assertSame(
            'https://www.vitobusinessos.com'.route('directory.index', [], false),
            $destination
        );
    }

    public function test_owner_with_tenant_context_is_redirected_to_app_surface(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'casa-brasa-bistro',
        ]);

        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $destination = AuthRedirector::destinationFor($owner);

        $this->assertSame(
            'https://app.vitobusinessos.com'.route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false),
            $destination
        );
    }

    public function test_super_admin_is_redirected_to_admin_surface(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);

        $superAdmin = User::factory()->superAdmin()->create();

        $destination = AuthRedirector::destinationFor($superAdmin);

        $this->assertSame(
            'https://admin.vitobusinessos.com'.route('filament.admin.pages.dashboard', [], false),
            $destination
        );
    }

    public function test_customer_with_tenant_context_but_no_business_membership_is_redirected_to_directory(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);

        $legacyUser = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'barberia-norte-pro',
        ]);

        $legacyUser->forceFill(['tenant_id' => $tenant->id])->save();

        $destination = AuthRedirector::destinationFor($legacyUser);

        $this->assertSame(
            'https://www.vitobusinessos.com'.route('directory.index', [], false),
            $destination
        );
    }

    public function test_profile_destination_follows_surface_split_rules(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $this->assertSame(
            'https://www.vitobusinessos.com'.route('directory.index', [], false),
            AuthRedirector::profileDestinationFor($customer)
        );

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $this->assertSame(
            route('filament.app.auth.profile', [], false),
            AuthRedirector::profileDestinationFor($owner)
        );
    }

    public function test_owner_redirect_prefers_same_origin_when_forwarded_host_differs_from_configured_surface_origin(): void
    {
        config([
            'app.platform_directory_url' => 'https://vitobusinessos.vercel.app',
            'app.platform_app_url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_admin_url' => 'https://vito-business-os-production.up.railway.app',
        ]);

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'demo-ai-guide-hn',
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $request = Request::create('https://vito-business-os-production.up.railway.app/login', 'POST');
        $request->headers->set('x-forwarded-host', 'vitobusinessos.vercel.app');
        $this->app->instance('request', $request);

        $destination = AuthRedirector::destinationFor($owner);

        $this->assertSame(
            route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false),
            $destination
        );
    }

    public function test_local_forwarded_host_does_not_override_configured_surface_origin(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $request = Request::create('http://127.0.0.1:8011/login', 'POST');
        $request->headers->set('x-forwarded-host', '127.0.0.1:8011');
        $this->app->instance('request', $request);

        $destination = AuthRedirector::destinationFor($customer);

        $this->assertSame(
            'https://www.vitobusinessos.com'.route('directory.index', [], false),
            $destination
        );
    }
}
