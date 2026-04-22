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
        $response->assertRedirect('https://www.vitobusinessos.com'.route('directory.index', [], false));
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
            'https://app.vitobusinessos.com'.route('filament.app.pages.dashboard', ['tenant' => 'mi-negocio'], false)
        );
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
