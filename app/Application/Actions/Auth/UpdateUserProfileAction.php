<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Identity\Commands\UpdateUserProfileCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use RuntimeException;

final readonly class UpdateUserProfileAction
{
    public function __construct(
        private CommandBusInterface $commandBus
    ) {}

    /**
     * @param  array{name: string, email: string}  $input
     */
    public function execute(TenantAwareAuthenticatable $user, array $input): void
    {
        $tenantId = (string) ($user->getTenantId() ?? '');

        if ($tenantId === '') {
            throw new RuntimeException('Cannot update profile without tenant context.');
        }

        $this->commandBus->dispatch(
            new UpdateUserProfileCommand(
                userId: (string) $user->getAuthIdentifier(),
                requestingTenantId: $tenantId,
                name: $input['name'],
                email: $input['email'],
            )
        );
    }
}
