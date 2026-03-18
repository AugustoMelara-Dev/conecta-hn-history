<?php

declare(strict_types=1);

namespace App\Application\Identity\Decorators;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use App\Application\Shared\Contracts\AtomicIdempotencyInterface;

/**
 * IdempotentRegisterUserHandler - Pure DIP-Compliant Deduplication Guard
 *
 * Intercepts the command, checks for existing results, and manages distributed
 * locks to ensure "Exactly-Once" processing semantics.
 *
 * CRITICAL: Uses IdempotencyServiceInterface (Application contract)
 * instead of any Infrastructure implementation.
 */
final readonly class IdempotentRegisterUserHandler implements RegisterUserHandlerInterface
{
    public function __construct(
        private RegisterUserHandlerInterface $inner,
        private AtomicIdempotencyInterface $service
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        // 1. RESOLVE KEY & HASH
        $key = $command->getIdempotencyKey();

        // Hash payload to prevent reusing same key for different request
        $hash = hash('xxh128', json_encode([
            'email' => $command->email,
            'slug' => $command->slug,
            'source' => $command->source,
        ]));

        // 2. ATOMIC EXECUTION (The Reaper Protocol)
        // We use a NIL UUID for Tenant ID since registration is a Public/Global action
        // before the tenant actually exists.
        return $this->service->execute(
            idempotencyKey: $key,
            tenantId: '00000000-0000-0000-0000-000000000000',
            commandHash: $hash,
            operation: fn () => $this->inner->handle($command)
        );
    }
}
