<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Common\Contracts\IdempotencyServiceInterface;

/**
 * IdempotentGenerateOtpHandler - Distributed State Protection Decorator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  LISKOV COMPLIANT: Implements same interface as decorated Handler.      ║
 * ║  ATOMIC LOCKING: Check-inside-lock pattern prevents TOCTOU race.        ║
 * ║  12-FACTOR: TTL injected via constructor, not hardcoded.                ║
 * ║  TENANT PARTITIONED: Cache key includes TenantID for isolation.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class IdempotentGenerateOtpHandler implements GenerateOtpHandlerInterface
{
    public function __construct(
        /** Inner handler - typed as interface for polymorphism */
        private GenerateOtpHandlerInterface $inner,
        private IdempotencyServiceInterface $idempotencyService,
        /** Idempotency TTL in seconds (previously hardcoded as 86400) */
        private int $idempotencyTtlSeconds,
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        // 1. LEGACY / BYPASS MODE - No idempotency key provided
        if ($command->idempotencyKey === null) {
            $this->inner->handle($command);
            return;
        }

        // 2. DETERMINISTIC KEY GENERATION (Tenant Partitioning)
        // Ensures no collision between tenants even if they send same idempotencyKey
        $rawKey = "otp_gen:{$command->tenantId}:{$command->idempotencyKey}";
        $cacheKey = hash('xxh128', $rawKey);

        // ═══════════════════════════════════════════════════════════════════
        // 3. ATOMIC LOCK-FIRST PATTERN (Anti-TOCTOU)
        // CRITICAL: The has() check MUST be INSIDE the lock closure
        // This eliminates the race condition where two requests both pass
        // has() check before either acquires the lock
        // ═══════════════════════════════════════════════════════════════════
        $this->idempotencyService->lock($cacheKey, function () use ($command, $cacheKey) {
            
            // 4. CHECK INSIDE LOCK (Atomic verification)
            if ($this->idempotencyService->has($cacheKey)) {
                // Already processed - exit without re-executing
                return;
            }

            // 5. EXECUTE BUSINESS LOGIC (Only one process can reach here per key)
            $this->inner->handle($command);

            // 6. STORE RESULT (Mark as "Done" for future requests)
            $this->idempotencyService->store($cacheKey, true, $this->idempotencyTtlSeconds);
        });
    }
}
