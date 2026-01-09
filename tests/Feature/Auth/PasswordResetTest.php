<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * PasswordResetTest - Password Reset Flow Tests
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FIXED: Uses proper token extraction pattern instead of callback.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);

        // Extract notification from fake
        $notifications = Notification::sent($user, ResetPassword::class);
        $this->assertNotEmpty($notifications, 'No ResetPassword notification was sent.');

        $notification = $notifications->first();
        $token = $notification->token;

        $response = $this->get('/reset-password/' . $token);

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);

        // Extract token from notification
        $notifications = Notification::sent($user, ResetPassword::class);
        $this->assertNotEmpty($notifications, 'No ResetPassword notification was sent.');

        $notification = $notifications->first();
        $token = $notification->token;

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login'));
    }
}

