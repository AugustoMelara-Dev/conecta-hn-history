<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $response = $this->get('/reset-password/'.$token);

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

    public function test_password_reset_revokes_all_api_tokens_and_rotates_remember_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['remember_token' => 'known-remember-token']);
        $user->createToken('stolen-device', ['tenant:'.($user->tenant_id ?? 'none')]);
        $user->createToken('second-device', ['tenant:'.($user->tenant_id ?? 'none')]);
        DB::table('sessions')->insert([
            'id' => 'stolen-web-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'stolen',
            'last_activity' => time(),
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);
        $notification = Notification::sent($user, ResetPassword::class)->first();

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertNotSame('known-remember-token', $user->remember_token);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-web-session']);
    }

    public function test_password_reset_request_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'unknown@example.test',
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', trans(Password::RESET_LINK_SENT));
        Notification::assertNothingSent();
    }
}
