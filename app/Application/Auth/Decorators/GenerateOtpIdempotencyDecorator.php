<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Shared\Contracts\AtomicIdempotencyInterface;

/**
 * GenerateOtpIdempotencyDecorator - Concurrency Control Layer
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  RUSSIAN DOLL: This decorator wraps the core handler with idempotency.  â•‘
 * â•‘  RESPONSIBILITY: Prevent duplicate processing of the same command.      â•‘
 * â•‘  TRANSPARENCY: The core handler is completely unaware of this layer.    â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class GenerateOtpIdempotencyDecorator implements GenerateOtpHandlerInterface
{
    public function __construct(
        private GenerateOtpHandlerInterface $inner,
        private AtomicIdempotencyInterface $atomicService
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
            idempotencyKey: $command->idempotencyKey,
            tenantId: $command->tenantId,
            commandHash: $payloadHash,
            operation: function () use ($command) {
                $this->inner->handle($command);

                // Return success marker for idempotency record
                return ['status' => 'success', 'processed_at' => \Carbon\CarbonImmutable::now()->toIso8601String()];
            }
        );
    }
}
