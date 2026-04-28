<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_ziggy_origin_uses_public_proxy_surface(): void
    {
        config([
            'app.url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_directory_url' => 'https://vitobusinessos.vercel.app',
            'app.platform_app_url' => 'https://vitobusinessos.vercel.app',
        ]);

        $response = $this->withHeaders([
            'Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Proto' => 'https',
        ])->get('/login');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('"url":"https:\/\/vitobusinessos.vercel.app"', $content);
        $this->assertStringNotContainsString('"url":"https:\/\/vito-business-os-production.up.railway.app"', $content);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
        ]);

        $user = User::factory()->create();
        $token = 'auth-login-token';

        $response = $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('directory.index', [], false));
    }

    public function test_tenant_owners_are_redirected_to_the_filament_app_panel_after_login(): void
    {
        config([
            'app.platform_directory_url' => 'https://www.vitobusinessos.com',
            'app.platform_app_url' => 'https://app.vitobusinessos.com',
        ]);

        $tenantOwner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $tenantOwner->id,
            'slug' => 'mi-negocio',
        ]);
        $tenantOwner->forceFill(['tenant_id' => $tenant->id])->save();
        $token = 'auth-login-tenant-token';

        $response = $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => $tenantOwner->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(
            route('filament.app.pages.dashboard', ['tenant' => 'mi-negocio'], false)
        );
    }

    public function test_super_admins_are_redirected_to_the_filament_admin_panel_after_login(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);
        $token = 'auth-login-super-admin-token';

        $response = $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => $superAdmin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('filament.admin.pages.dashboard', [], false));
    }

    public function test_profile_route_redirects_customer_to_directory_surface(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $response = $this->actingAs($customer)->get('/profile');

        $response->assertRedirect(route('directory.index', absolute: false));
    }

    public function test_profile_route_redirects_tenant_owner_to_filament_profile(): void
    {
        $tenantOwner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $tenantOwner->id,
            'slug' => 'owner-surface-check',
        ]);
        $tenantOwner->forceFill(['tenant_id' => $tenant->id])->save();

        $response = $this->actingAs($tenantOwner)->get('/profile');

        $response->assertRedirect(route('filament.app.auth.profile', [], false));
    }

    public function test_profile_route_redirects_super_admin_to_admin_profile(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $response = $this->actingAs($superAdmin)->get('/profile');

        $response->assertRedirect(route('filament.admin.auth.profile', [], false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();
        $token = 'auth-login-invalid-token';

        $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        $token = 'auth-logout-token';

        $response = $this->withSession(['_token' => $token])
            ->actingAs($user)
            ->post('/logout', ['_token' => $token]);

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
