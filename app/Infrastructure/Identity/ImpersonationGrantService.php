<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Application\Security\PrivilegedActionGuard;
use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\ImpersonationGrant;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ImpersonationGrantService
{
    public const SESSION_GRANT_ID = 'impersonation_grant_id';

    public const SESSION_BINDING_SECRET = 'impersonation_binding_secret';

    public function __construct(
        private PrivilegedActionGuard $guard,
        private AuthenticatedSessionRegistry $sessions,
    ) {}

    public function start(
        Request $request,
        User $operator,
        Tenant $tenant,
        User $target,
        string $currentPassword,
        string $reason,
    ): ImpersonationGrant {
        $normalizedReason = $this->guard->authorizeImpersonation(
            isSuperAdmin: $operator->isSuperAdmin(),
            credentialsVerified: Hash::check($currentPassword, (string) $operator->password),
            mfaVerified: $this->hasRecentMfaVerification($request, $operator),
            reason: $reason,
        );
        $this->assertStartBoundary($request, $operator, $tenant, $target);

        if ($request->session()->getId() === '') {
            throw new DomainException('Impersonation requires an active session.');
        }

        $bindingSecret = bin2hex(random_bytes(32));
        $request->session()->put(self::SESSION_BINDING_SECRET, $bindingSecret);
        $mfaVerification = $request->session()->get('auth.two_factor_verified');
        $grant = DB::transaction(function () use (
            $operator,
            $tenant,
            $target,
            $bindingSecret,
            $normalizedReason,
        ): ImpersonationGrant {
            return ImpersonationGrant::query()->create([
                'operator_user_id' => (string) $operator->getKey(),
                'tenant_id' => (string) $tenant->getKey(),
                'target_user_id' => (string) $target->getKey(),
                'session_id_hash' => $this->sessions->sessionHash($bindingSecret),
                'reason' => $normalizedReason,
                'expires_at' => now()->addMinutes(
                    max(1, (int) config('security.impersonation.ttl_minutes', 15)),
                ),
                'started_at' => now(),
            ]);
        });

        try {
            $this->sessions->markCurrentRevoked($request, $operator, 'impersonation_started');
            Auth::guard('web')->login($target);
            $request->session()->regenerate();
            $request->session()->put([
                self::SESSION_GRANT_ID => (string) $grant->getKey(),
                'impersonator_id' => (string) $operator->getKey(),
                'impersonator_name' => (string) $operator->name,
                'impersonation_id' => (string) $grant->getKey(),
                'impersonation_reason' => $normalizedReason,
                'impersonated_tenant_id' => (string) $tenant->getKey(),
                'impersonation_expires_at' => $grant->expires_at?->toIso8601String(),
                'impersonator_mfa_verified' => $mfaVerification,
            ]);
            $request->session()->forget([
                'auth.password_confirmed_at',
                'password_hash_check',
                'auth.two_factor_verified',
            ]);

            $this->sessions->start($request, $target);
            $this->audit($grant, $operator, $target, 'started', $request);

            return $grant->refresh();
        } catch (Throwable $exception) {
            $grant->forceFill([
                'revoked_at' => now(),
                'ended_at' => now(),
            ])->save();
            Auth::guard('web')->login($operator);
            $this->clearSession($request);
            $request->session()->regenerate();
            $this->sessions->start($request, $operator);

            throw $exception;
        }
    }

    public function state(Request $request): string
    {
        $grantId = trim((string) $request->session()->get(self::SESSION_GRANT_ID, ''));
        if ($grantId === '') {
            if ($request->session()->has('impersonator_id')) {
                throw new DomainException('Legacy impersonation session is no longer valid.');
            }

            return 'none';
        }

        $grant = $this->grantForSession($request, $grantId);

        return match (true) {
            $grant->revoked_at !== null => 'revoked',
            $grant->ended_at !== null => 'ended',
            $grant->expires_at === null || $grant->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    public function restoreOperator(Request $request, string $termination = 'user_exit'): User
    {
        $grantId = trim((string) $request->session()->get(self::SESSION_GRANT_ID, ''));
        if ($grantId === '') {
            throw new DomainException('No durable impersonation grant is active.');
        }

        $grant = $this->grantForSession($request, $grantId, allowInactive: true);
        $operator = $grant->operator;
        $target = $grant->target;
        if (! $operator instanceof User || ! $operator->isSuperAdmin() || ! $target instanceof User) {
            throw new DomainException('Impersonation operator or target is no longer valid.');
        }

        if ($grant->ended_at === null) {
            $grant->forceFill(['ended_at' => now()])->save();
        }
        $this->audit(
            $grant,
            $operator,
            $target,
            $termination === 'expired' ? 'expired' : 'ended',
            $request,
            ['termination' => $termination],
        );

        $savedMfaVerification = $request->session()->get('impersonator_mfa_verified');
        $this->sessions->markCurrentRevoked($request, $target, 'impersonation_ended');
        Auth::guard('web')->logout();
        Auth::guard('web')->login($operator);
        $this->clearSession($request);
        $request->session()->regenerate();
        if ($this->isValidSavedMfa($savedMfaVerification, $operator)) {
            $request->session()->put('auth.two_factor_verified', $savedMfaVerification);
        }
        $this->sessions->start($request, $operator);

        return $operator;
    }

    public function revoke(User $actor, ImpersonationGrant $grant, Request $request, string $reason): void
    {
        $normalizedReason = trim($reason);
        if (! $actor->isSuperAdmin() || mb_strlen($normalizedReason) < 10) {
            throw new DomainException('Only a platform administrator may revoke a grant with an audit reason.');
        }
        if ($grant->ended_at !== null || $grant->revoked_at !== null) {
            throw new DomainException('Impersonation grant is already inactive.');
        }

        $grant->forceFill(['revoked_at' => now()])->save();
        $target = $grant->target;
        if ($target instanceof User) {
            $this->audit($grant, $actor, $target, 'revoked', $request, [
                'revocation_reason' => $normalizedReason,
            ]);
        }
    }

    public function failClosed(Request $request): void
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->sessions->markCurrentRevoked($request, $user, 'invalid_impersonation_grant');
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function assertStartBoundary(
        Request $request,
        User $operator,
        Tenant $tenant,
        User $target,
    ): void {
        if ($request->session()->has(self::SESSION_GRANT_ID)
            || $request->session()->has('impersonator_id')) {
            throw new DomainException('Nested impersonation is forbidden.');
        }
        if ($target->isSuperAdmin()
            || $target->is($operator)
            || ! $target->canAccessTenant($tenant)
            || (string) $tenant->getKey() === '') {
            throw new DomainException('Impersonation target is outside the explicit tenant boundary.');
        }

        $sessionHash = $this->sessions->sessionHash(
            (string) $request->session()->get(self::SESSION_BINDING_SECRET, ''),
        );
        if (ImpersonationGrant::query()
            ->where('session_id_hash', $sessionHash)
            ->whereNull('ended_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists()) {
            throw new DomainException('An impersonation grant already owns this session.');
        }
    }

    private function grantForSession(
        Request $request,
        string $grantId,
        bool $allowInactive = false,
    ): ImpersonationGrant {
        $grant = ImpersonationGrant::query()
            ->with(['operator', 'target'])
            ->find($grantId);
        if (! $grant instanceof ImpersonationGrant) {
            throw new DomainException('Impersonation grant does not exist.');
        }
        $bindingSecret = trim((string) $request->session()->get(
            self::SESSION_BINDING_SECRET,
            '',
        ));
        if ($bindingSecret === '' || ! hash_equals(
            (string) $grant->session_id_hash,
            $this->sessions->sessionHash($bindingSecret),
        )) {
            throw new DomainException('Impersonation grant is bound to another session.');
        }
        if ((string) $request->user()?->getKey() !== (string) $grant->target_user_id) {
            throw new DomainException('Impersonation target does not match the authenticated identity.');
        }
        if (! $allowInactive && ($grant->ended_at !== null || $grant->revoked_at !== null)) {
            return $grant;
        }

        return $grant;
    }

    private function hasRecentMfaVerification(Request $request, User $operator): bool
    {
        return $operator->hasEnabledTwoFactorAuthentication()
            && $this->isValidSavedMfa(
                $request->session()->get('auth.two_factor_verified'),
                $operator,
            );
    }

    private function isValidSavedMfa(mixed $verification, User $operator): bool
    {
        $maxAge = max(60, (int) config('security.impersonation.mfa_max_age_seconds', 600));

        return is_array($verification)
            && hash_equals(
                (string) $operator->getAuthIdentifier(),
                (string) ($verification['user_id'] ?? ''),
            )
            && is_numeric($verification['verified_at'] ?? null)
            && (int) $verification['verified_at'] >= now()->getTimestamp() - $maxAge;
    }

    private function clearSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_GRANT_ID,
            self::SESSION_BINDING_SECRET,
            'impersonator_id',
            'impersonator_name',
            'impersonation_id',
            'impersonation_reason',
            'impersonated_tenant_id',
            'impersonation_expires_at',
            'impersonator_mfa_verified',
            'auth.password_confirmed_at',
            'password_hash_check',
            'auth.two_factor_verified',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function audit(
        ImpersonationGrant $grant,
        User $actor,
        User $target,
        string $event,
        Request $request,
        array $extra = [],
    ): void {
        $properties = [
            'impersonation_id' => (string) $grant->getKey(),
            'tenant_id' => (string) $grant->tenant_id,
            'target_user_id' => (string) $grant->target_user_id,
            'operator_user_id' => (string) $grant->operator_user_id,
            'reason' => (string) $grant->reason,
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'ip_address_hash' => $request->ip() === null
                ? null
                : hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ...$extra,
        ];
        $description = 'admin_impersonation_'.$event;

        activity('security')
            ->causedBy($actor)
            ->performedOn($target)
            ->withProperties($properties)
            ->log($description);

        try {
            Log::channel('security')->warning('security.impersonation.'.$event, $properties);
        } catch (Throwable $exception) {
            // A secondary transport must not interrupt an already-persisted
            // security lifecycle transition.
            try {
                Log::warning('Security audit transport unavailable.', [
                    'event' => $description,
                    'impersonation_id' => (string) $grant->getKey(),
                    'exception_class' => $exception::class,
                ]);
            } catch (Throwable) {
                // The durable activity record above remains the source of truth.
            }
        }
    }
}
