<?php

declare(strict_types=1);

namespace App\Application\Common\Decorators;

use App\Application\Common\Contracts\AtomicIdempotencyInterface;
use App\Application\Common\Contracts\HashableCommandInterface;
use InvalidArgumentException;

/**
 * AtomicIdempotencyDecorator
 *
 * Standardized generic decorator for Atomic Protocol.
 * Requires the command to implement HashableCommand.
 */
final class AtomicIdempotencyDecorator
{
    public function __construct(
        private object $inner,
        private AtomicIdempotencyInterface $service
    ) {}

    public function handle(object $command): mixed
    {
        if (! ($command instanceof HashableCommand)) {
            // Fallback or Error?
            // For standard hardening, we enforce the contract.
            throw new InvalidArgumentException('Command must implement HashableCommand to use AtomicIdempotencyDecorator.');
        }

        return $this->service->execute(
            idempotencyKey: $command->getIdempotencyKey(),
            tenantId: $command->getTenantId(),
            commandHash: $command->getCommandHash(),
            operation: fn () => $this->inner->handle($command)
        );
    }
}
