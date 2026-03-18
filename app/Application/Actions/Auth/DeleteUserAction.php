<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class DeleteUserAction
{
    public function __construct(
        private CommandBusInterface $commandBus
    ) {}

    public function execute(TenantAwareAuthenticatable $user): void
    {
        $tenantId = (string) ($user->getTenantId() ?? '');

        if ($tenantId === '') {
            throw new RuntimeException('Cannot delete profile without tenant context.');
        }

        $this->commandBus->dispatch(
            new DeleteUserCommand(
                userId: (string) $user->getAuthIdentifier(),
                requestingTenantId: $tenantId,
                idempotencyKey: Uuid::uuid7()->toString(),
            )
        );
    }
}
