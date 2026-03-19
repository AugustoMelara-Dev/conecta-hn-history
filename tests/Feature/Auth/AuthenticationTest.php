<?php

namespace Tests\Feature\Auth;

use App\Models\User;
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
        $token = 'auth-login-token';

        $response = $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
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
