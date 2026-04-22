<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthRedirectorTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_legacy_customer_with_tenant_context_is_redirected_to_app_surface(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
            'app.platform_admin_url' => 'https://admin.vitobusinessos.com',
        ]);

        $legacyUser = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);
        $tenant = Tenant::factory()->forUser($legacyUser)->create([
            'slug' => 'barberia-norte-pro',
        ]);

        $legacyUser->forceFill(['tenant_id' => $tenant->id])->save();

        $destination = AuthRedirector::destinationFor($legacyUser);

        $this->assertSame(
            'https://app.vitobusinessos.com'.route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false),
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
}
