<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Application\Auth\Commands\ResetUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * ResetPasswordAction - Authentication Password Reset Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTER PATTERN: Bridges Fortify (Infra) to Application Layer.         ║
 * ║  PURE DELEGATION: Assert → Map → Dispatch. Zero business logic.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Extracted hashing, persistence, and event dispatch to
 * ResetUserPasswordHandler in the Application Layer.
 * DECOUPLED: Uses TenantAwareAuthenticatable for unified Identity + Tenancy assertion.
 */
final readonly class ResetPasswordAction implements ResetsUserPasswords
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private \App\Application\Shared\Contracts\TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * Reset the user's forgotten password.
     *
     * @param  mixed  $user  Fortify contract forces mixed; we assert TenantAwareAuthenticatable at runtime.
     * @param  array<string, string>  $input
     *
     * @throws \InvalidArgumentException If $user is not TenantAwareAuthenticatable.
     * @throws \RuntimeException If user has no tenant context.
     */
    public function reset($user, array $input): void
    {
        // PRODUCTION SAFETY: Explicit guard replaces assert() which is compiled
        // out in production (zend.assertions = -1). SEC-01 hardening.
        if (! $user instanceof TenantAwareAuthenticatable) {
            throw new \InvalidArgumentException('User must implement TenantAwareAuthenticatable.');
        }

        $tenantId = (string) $user->getTenantId();

        if (! $tenantId) {
            throw new \RuntimeException('Cannot reset password: user has no tenant context.');
        }

        // RATE LIMITING: 5 resets per minute per tenant
        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: $tenantId,
            maxAttempts: 5,
            decayMinutes: 1
        );

        $this->commandBus->dispatch(
            new ResetUserPasswordCommand(
                userId: (string) $user->getAuthIdentifier(),
                password: $input['password'],
                tenantId: $tenantId,
            )
        );
    }
}
