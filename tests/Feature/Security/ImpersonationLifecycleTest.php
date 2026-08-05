<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Infrastructure\Identity\ImpersonationGrantService;
use App\Models\ImpersonationGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ImpersonationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('activitylog.enabled', true);
    }

    public function test_durable_grant_starts_and_exits_with_mfa_session_binding_and_audit(): void
    {
        [$admin, $target, $tenant] = $this->fixture();
        $this->registerStartRoute();

        $start = $this->actingAs($admin)
            ->withSession($this->mfaSession($admin))
            ->post('/_test/impersonation/'.$tenant->getKey().'/'.$target->getKey(), [
                'current_password' => 'password',
                'reason' => 'Diagn�stico solicitado por soporte autorizado.',
            ]);

        $start->assertRedirect('/app');
        $this->assertAuthenticatedAs($target);
        $start->assertSessionHas(ImpersonationGrantService::SESSION_GRANT_ID);
        $grantId = (string) session(ImpersonationGrantService::SESSION_GRANT_ID);
        $grant = ImpersonationGrant::query()->findOrFail($grantId);

        self::assertSame((string) $admin->getKey(), (string) $grant->operator_user_id);
        self::assertSame((string) $target->getKey(), (string) $grant->target_user_id);
        self::assertSame((string) $tenant->getKey(), (string) $grant->tenant_id);
        self::assertTrue($grant->expires_at->isFuture());
        self::assertNull($grant->ended_at);
        self::assertNull($grant->revoked_at);
        self::assertSame(
            app(AuthenticatedSessionRegistry::class)
                ->sessionHash((string) session(ImpersonationGrantService::SESSION_BINDING_SECRET)),
            (string) $grant->session_id_hash,
        );
        $this->assertDatabaseHas('activity_log', [
            'description' => 'admin_impersonation_started',
            'causer_id' => (string) $admin->getKey(),
            'subject_id' => (string) $target->getKey(),
        ]);

        $leave = $this->post(route('impersonate.leave'));

        $leave->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $leave->assertSessionMissing([
            ImpersonationGrantService::SESSION_GRANT_ID,
            'impersonator_id',
            'impersonation_reason',
            'impersonated_tenant_id',
            'impersonator_mfa_verified',
        ]);
        self::assertNotNull($grant->fresh()->ended_at);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'admin_impersonation_ended',
            'causer_id' => (string) $admin->getKey(),
            'subject_id' => (string) $target->getKey(),
        ]);
        $leave->assertSessionHas('auth.two_factor_verified.user_id', (string) $admin->getKey());
    }

    public function test_expired_grant_automatically_restores_operator_and_records_expiry(): void
    {
        [$admin, $target, $tenant] = $this->fixture();
        $this->registerStartRoute();
        Route::middleware(['web', 'auth'])->get('/_test/after-impersonation', static fn () => 'ok');

        $this->actingAs($admin)
            ->withSession($this->mfaSession($admin))
            ->post('/_test/impersonation/'.$tenant->getKey().'/'.$target->getKey(), [
                'current_password' => 'password',
                'reason' => 'Reproducir incidencia reportada por el comercio.',
            ])
            ->assertRedirect('/app');

        $grant = ImpersonationGrant::query()->findOrFail(
            (string) session(ImpersonationGrantService::SESSION_GRANT_ID),
        );
        $grant->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->get('/_test/after-impersonation')->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        self::assertNotNull($grant->fresh()->ended_at);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'admin_impersonation_expired',
            'causer_id' => (string) $admin->getKey(),
            'subject_id' => (string) $target->getKey(),
        ]);
    }

    public function test_revocation_is_durable_audited_and_ends_the_target_session_on_next_request(): void
    {
        [$admin, $target, $tenant] = $this->fixture();
        $this->registerStartRoute();
        Route::middleware(['web', 'auth'])->get('/_test/revoked-impersonation', static fn () => 'ok');

        $this->actingAs($admin)
            ->withSession($this->mfaSession($admin))
            ->post('/_test/impersonation/'.$tenant->getKey().'/'.$target->getKey(), [
                'current_password' => 'password',
                'reason' => 'Validar configuraci�n solicitada por el cliente.',
            ]);
        $grant = ImpersonationGrant::query()->findOrFail(
            (string) session(ImpersonationGrantService::SESSION_GRANT_ID),
        );

        app(ImpersonationGrantService::class)->revoke(
            $admin,
            $grant,
            request(),
            'Revocaci�n inmediata solicitada por seguridad.',
        );
        $this->assertDatabaseHas('activity_log', [
            'description' => 'admin_impersonation_revoked',
            'causer_id' => (string) $admin->getKey(),
            'subject_id' => (string) $target->getKey(),
        ]);

        $this->get('/_test/revoked-impersonation')->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        self::assertNotNull($grant->fresh()->ended_at);
        self::assertNotNull($grant->fresh()->revoked_at);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'admin_impersonation_ended',
            'causer_id' => (string) $admin->getKey(),
            'subject_id' => (string) $target->getKey(),
        ]);
    }

    public function test_start_rejects_missing_recent_mfa_and_cross_tenant_target(): void
    {
        [$admin, $target, $tenant] = $this->fixture();
        $otherTenant = Tenant::factory()->create();
        $this->registerStartRoute();

        $this->actingAs($admin)
            ->post('/_test/impersonation/'.$tenant->getKey().'/'.$target->getKey(), [
                'current_password' => 'password',
                'reason' => 'Investigar incidente con autorizaci�n del cliente.',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($admin)
            ->withSession($this->mfaSession($admin))
            ->post('/_test/impersonation/'.$otherTenant->getKey().'/'.$target->getKey(), [
                'current_password' => 'password',
                'reason' => 'Investigar incidente con autorizaci�n del cliente.',
            ])
            ->assertServerError();

        self::assertSame(0, ImpersonationGrant::query()->count());
    }

    public function test_invalid_or_legacy_session_fails_closed_instead_of_trusting_session_fields(): void
    {
        [$admin, $target] = $this->fixture();
        Route::middleware(['web', 'auth'])->get('/_test/legacy-impersonation', static fn () => 'ok');

        $response = $this->actingAs($target)
            ->withSession([
                'impersonator_id' => (string) $admin->getKey(),
                'impersonation_id' => 'legacy-only',
            ])
            ->get('/_test/legacy-impersonation');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    private function registerStartRoute(): void
    {
        Route::middleware(['web', 'auth'])->post(
            '/_test/impersonation/{tenant}/{target}',
            function (
                Request $request,
                Tenant $tenant,
                User $target,
                ImpersonationGrantService $impersonation,
            ) {
                $operator = $request->user();
                self::assertInstanceOf(User::class, $operator);
                $impersonation->start(
                    $request,
                    $operator,
                    $tenant,
                    $target,
                    (string) $request->input('current_password'),
                    (string) $request->input('reason'),
                );

                return redirect('/app');
            },
        );
    }

    /**
     * @return array{User, User, Tenant}
     */
    private function fixture(): array
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => fake()->unique()->safeEmail(),
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        config()->set('auth.super_admins', [$admin->email]);
        $target = User::factory()->create();
        $tenant = Tenant::factory()->forUser($target)->create();
        $target->forceFill(['tenant_id' => $tenant->getKey()])->save();

        return [$admin->refresh(), $target->refresh(), $tenant];
    }

    /**
     * @return array<string, mixed>
     */
    private function mfaSession(User $admin): array
    {
        return [
            'auth.two_factor_verified' => [
                'user_id' => (string) $admin->getKey(),
                'verified_at' => now()->getTimestamp(),
            ],
        ];
    }
}
