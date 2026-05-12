<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Common\Contracts\AtomicIdempotencyInterface;
use App\Infrastructure\Shared\Context\IdempotencyContext;

/**
 * AtomicIdempotencyDecorator - Concurrency Control Layer
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RUSSIAN DOLL: This decorator wraps the core handler with idempotency.  ║
 * ║  RESPONSIBILITY: Prevent duplicate processing of the same command.      ║
 * ║  TRANSPARENCY: The core handler is completely unaware of this layer.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class AtomicIdempotencyDecorator implements GenerateOtpHandlerInterface
{
    public function __construct(
        private GenerateOtpHandlerInterface $inner,
        private AtomicIdempotencyInterface $atomicService,
        private IdempotencyContext $idempotencyContext
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        // Calculate Command Hash for Integrity Check
        $payloadHash = hash('sha256', json_encode([
            'identifier' => $command->identifier,
            'type' => $command->type,
            'tenantId' => $command->tenantId,
            'ipAddress' => $command->ipAddress,
            'userAgent' => $command->userAgent,
        ]));

        $this->atomicService->execute(
            idempotencyKey: $this->idempotencyContext->hasKey()
                ? $this->idempotencyContext->getKey()
                : $command->getIdempotencyKey(),
            tenantId: $command->tenantId,
            commandHash: $payloadHash,
            operation: function () use ($command) {
                $this->inner->handle($command);

                // Return success marker for idempotency record
                return ['status' => 'success', 'processed_at' => now()->toIso8601String()];
            }
        );
    }
}
