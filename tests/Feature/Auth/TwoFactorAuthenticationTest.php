<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\RequireTwoFactorSession;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

final class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_stops_at_two_factor_challenge_without_authenticating_session(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user, ['recovery-one']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login', absolute: false));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_recovery_code_completes_challenge_and_is_rotated(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user, ['recovery-one']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login', absolute: false));

        $response = $this->post('/two-factor-challenge', [
            'recovery_code' => 'recovery-one',
        ]);
        $response->assertRedirect('/profile');
        $response->assertSessionHas('auth.two_factor_verified.user_id', $user->id);

        $this->assertAuthenticatedAs($user);
        self::assertNotContains('recovery-one', $user->refresh()->recoveryCodes());
        self::assertNotNull(DB::table('authenticated_sessions')
            ->where('user_id', $user->id)
            ->whereNotNull('mfa_verified_at')
            ->value('id'));
    }

    public function test_panel_guard_rejects_enrolled_user_without_a_verified_two_factor_session(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user, ['recovery-one']);
        Route::middleware(['web', 'auth', RequireTwoFactorSession::class])
            ->get('/__security_test/two-factor-panel', fn () => response('ok'));

        $this->actingAs($user)
            ->get('/__security_test/two-factor-panel')
            ->assertRedirect(route('login', absolute: false));
        $this->assertGuest();

        $this->actingAs($user)
            ->withSession([
                'auth.two_factor_verified' => [
                    'user_id' => $user->id,
                    'verified_at' => now()->getTimestamp(),
                ],
            ])
            ->get('/__security_test/two-factor-panel')
            ->assertOk();
    }

    public function test_platform_admin_is_routed_to_mfa_setup_and_cannot_access_admin_until_confirmed(): void
    {
        $user = User::factory()->superAdmin()->create();
        config([
            'auth.super_admins' => [$user->email],
            'security.admin_mfa_required' => true,
        ]);

        self::assertFalse($user->canAccessPanel(filament()->getPanel('admin')));
        self::assertSame(
            route('profile.two-factor.show', absolute: false),
            app(CanonicalPanelUrlResolver::class)->forUser($user),
        );

        $this->enableTwoFactor($user, ['recovery-admin']);
        self::assertTrue($user->refresh()->canAccessPanel(filament()->getPanel('admin')));
        self::assertSame('/admin', app(CanonicalPanelUrlResolver::class)->forUser($user->refresh()));
    }

    public function test_required_platform_admin_cannot_disable_two_factor(): void
    {
        $user = User::factory()->superAdmin()->create();
        config([
            'auth.super_admins' => [$user->email],
            'security.admin_mfa_required' => true,
        ]);
        $this->enableTwoFactor($user, ['recovery-admin']);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('profile.two-factor.destroy'));

        $response->assertSessionHasErrors('two_factor');
        self::assertTrue($user->refresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_required_unenrolled_platform_admin_is_denied_by_two_factor_session_middleware(): void
    {
        $user = User::factory()->superAdmin()->create();
        config([
            'auth.super_admins' => [$user->email],
            'security.admin_mfa_required' => true,
        ]);
        Route::middleware(['web', 'auth', RequireTwoFactorSession::class])
            ->get('/__security_test/platform-tool', fn () => response('allowed'));

        $this->actingAs($user)
            ->get('/__security_test/platform-tool')
            ->assertRedirect(route('profile.two-factor.show', absolute: false))
            ->assertSessionHasErrors('two_factor');
    }

    public function test_disabling_mfa_revokes_tokens_and_other_authenticated_sessions(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user, ['recovery-one']);
        $user->createToken('privileged-device', ['tenant:test'], now()->addDay());
        $remoteSessionId = (string) Str::uuid();
        DB::table('authenticated_sessions')->insert([
            'id' => $remoteSessionId,
            'session_id_hash' => hash('sha256', 'remote'),
            'user_id' => $user->id,
            'current_tenant_id' => null,
            'device_name' => 'Remote',
            'ip_address_hash' => null,
            'user_agent' => 'Remote',
            'last_authenticated_at' => now()->subHour(),
            'mfa_verified_at' => now()->subHour(),
            'last_seen_at' => now()->subMinute(),
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('profile.two-factor.destroy'))
            ->assertRedirect(route('profile.edit'));

        self::assertFalse($user->refresh()->hasEnabledTwoFactorAuthentication());
        self::assertSame(0, $user->tokens()->count());
        self::assertNotNull(DB::table('authenticated_sessions')
            ->where('id', $remoteSessionId)
            ->value('revoked_at'));
    }

    /** @param list<string> $recoveryCodes */
    private function enableTwoFactor(User $user, array $recoveryCodes): void
    {
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes, JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
}
