<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Fortify\Actions\Authentication;

use App\Application\Auth\Commands\ResetUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * ResetPasswordAction - Infrastructure Adapter for Fortify
 *
 * ARCHITECTURAL BRIDGE: Bridges Fortify (Infra) to Application Layer.
 * Relocated from app/Actions/Authentication to Infrastructure layer.
 * Input Adapter only: Assert → Map → Dispatch. Zero business logic.
 */
final readonly class ResetPasswordAction implements ResetsUserPasswords
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function reset($user, array $input): void
    {
        if (! $user instanceof TenantAwareAuthenticatable) {
            throw new \InvalidArgumentException('User must implement TenantAwareAuthenticatable.');
        }

        $tenantId = (string) $user->getTenantId();

        if (! $tenantId) {
            throw new \RuntimeException('Cannot reset password: user has no tenant context.');
        }

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
