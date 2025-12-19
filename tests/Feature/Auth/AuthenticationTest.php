<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AuthenticationTest - Tests para el flujo de login/logout con Livewire
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: La pantalla de login se renderiza
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertSuccessful();
    }

    /**
     * Test: Los usuarios pueden autenticarse usando el formulario Livewire
     */
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('authenticate');

        $this->assertAuthenticated();
    }

    /**
     * Test: Los usuarios no pueden autenticarse con contraseña incorrecta
     */
    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    /**
     * Test: Los usuarios pueden cerrar sesión
     */
    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }
}
