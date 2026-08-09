<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(app(CanonicalPanelUrlResolver::class)->forUser($user));
    }

    public function test_inertia_login_uses_full_page_navigation_for_filament_panel(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => 'owner-panel',
            'is_active' => true,
        ]);

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $response = $this
            ->withHeader('X-Inertia', 'true')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', '/app/owner-panel');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
