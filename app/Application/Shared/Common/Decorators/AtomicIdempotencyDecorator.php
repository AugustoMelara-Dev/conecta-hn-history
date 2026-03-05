<?php

declare(strict_types=1);

namespace App\Application\Shared\Common\Decorators;

use App\Application\Shared\Contracts\AtomicIdempotencyInterface;
use App\Application\Shared\Contracts\HashableCommandInterface;
use InvalidArgumentException;

/**
 * AtomicIdempotencyDecorator
 *
 * Standardized generic decorator for Atomic Protocol.
 * Requires the command to implement HashableCommand.
 */
final readonly class AtomicIdempotencyDecorator
{
    public function __construct(
        private object $inner,
        private AtomicIdempotencyInterface $service
    ) {}

    public function handle(object $command): mixed
    {
        if (! ($command instanceof HashableCommandInterface)) {
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
