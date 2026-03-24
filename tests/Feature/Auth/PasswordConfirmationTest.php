<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = User::factory()->create();
        $token = 'confirm-password-token';

        $response = $this->withSession(['_token' => $token])
            ->actingAs($user)
            ->post('/confirm-password', [
                '_token' => $token,
                'password' => 'password',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->create();
        $token = 'confirm-password-invalid-token';

        $response = $this->withSession(['_token' => $token])
            ->actingAs($user)
            ->post('/confirm-password', [
                '_token' => $token,
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors();
    }
}
