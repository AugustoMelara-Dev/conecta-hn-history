<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
    use DatabaseTransactions;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();
        $token = 'forgot-password-token';

        $user = User::factory()->withTenant()->create();

        $response = $this->withSession(['_token' => $token])->post('/forgot-password', [
            '_token' => $token,
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();
        $token = 'forgot-password-screen-token';

        $user = User::factory()->withTenant()->create();

        $this->withSession(['_token' => $token])->post('/forgot-password', [
            '_token' => $token,
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);

        // Extract notification from fake
        $notifications = Notification::sent($user, ResetPassword::class);
        $this->assertGreaterThan(0, $notifications->count(), 'No ResetPassword notification was sent.');

        $notification = $notifications->first();
        $token = $notification->token;

        $response = $this->get('/reset-password/'.$token);

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();
        $requestToken = 'forgot-password-reset-token';
        $resetToken = 'reset-password-token';

        $user = User::factory()->withTenant()->create();

        $this->withSession(['_token' => $requestToken])->post('/forgot-password', [
            '_token' => $requestToken,
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);

        // Extract token from notification
        $notifications = Notification::sent($user, ResetPassword::class);
        $this->assertGreaterThan(0, $notifications->count(), 'No ResetPassword notification was sent.');

        $notification = $notifications->first();
        $token = $notification->token;

        $response = $this->withSession(['_token' => $resetToken])->post('/reset-password', [
            '_token' => $resetToken,
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login'));
    }
}
