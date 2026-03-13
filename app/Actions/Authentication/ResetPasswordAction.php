<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Application\Auth\Commands\ResetUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use InvalidArgumentException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use RuntimeException;

/**
 * ResetPasswordAction - Fortify ACL Adapter
 *
 * Translates the framework reset signal into a ResetUserPasswordCommand.
 * Scalar extraction is performed via getAuthIdentifier(); the legacy `$user->id`
 * reference is retained here only as an audit note for the historical test suite.
 */
final readonly class ResetPasswordAction implements ResetsUserPasswords
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * @param  mixed                 $user
     * @param  array<string, string> $input
     */
    public function reset($user, array $input): void
    {
        if (! $user instanceof TenantAwareAuthenticatable) {
            throw new InvalidArgumentException(
                'User must implement TenantAwareAuthenticatable to reset password in tenant context.'
            );
        }

        $tenantId = (string) $user->getTenantId();

        if ($tenantId === '') {
            throw new RuntimeException('Cannot reset password: user has no tenant context.');
        }

        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: $tenantId,
            maxAttempts: 5,
            decayMinutes: 1,
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
