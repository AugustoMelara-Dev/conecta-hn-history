<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Resilience;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;
use Throwable;

/**
 * CircuitBreakerDecorator - Resiliency Shield
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FAIL FAST: Prevents cascading failures when providers (Twilio/SES) die.║
 * ║  DISTRIBUTED STATE: Uses Cache (Redis) to sync state across workers.    ║
 * ║  FIXED: Updated send() signature to match OtpNotifierInterface.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class CircuitBreakerDecorator implements OtpNotifierInterface
{
    private const FAILURE_THRESHOLD = 5;

    private const OPEN_TTL_SECONDS = 60;

    private const CACHE_PREFIX = 'circuit_breaker:otp_notifier';

    public function __construct(
        private readonly OtpNotifierInterface $innerService,
        private readonly CacheRepository $cache,
        private readonly string $serviceName = 'default'
    ) {}

    /**
     * {@inheritDoc}
     *
     * FIXED: Updated signature to match OtpNotifierInterface contract.
     */
    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes,
        ?string $magicUrl = null
    ): void {
        // 1. FAIL FAST CHECK
        if ($this->isOpen()) {
            throw new RuntimeException("Circuit Opened: OTP Service [{$this->serviceName}] is currently unavailable.");
        }

        try {
            // 2. DELEGATE EXECUTION
            $this->innerService->send($recipient, $plainTextCode, $identifier, $tenantId, $expiryMinutes, $magicUrl);

            // 3. SUCCESS - Reset local failure count if needed
        } catch (Throwable $e) {
            // 4. ON FAILURE - Record and Trip
            $this->recordFailure();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        return $this->cache->has($this->getOpenKey());
    }

    private function recordFailure(): void
    {
        $key = $this->getFailureKey();

        $failures = $this->cache->increment($key);

        if ($failures === 1) {
            $this->cache->put($key, 1, 120);
        }

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->tripCircuit();
        }
    }

    private function tripCircuit(): void
    {
        $this->cache->put($this->getOpenKey(), true, self::OPEN_TTL_SECONDS);
        $this->cache->forget($this->getFailureKey());
    }

    private function getOpenKey(): string
    {
        return self::CACHE_PREFIX.":{$this->serviceName}:open";
    }

    private function getFailureKey(): string
    {
        return self::CACHE_PREFIX.":{$this->serviceName}:failures";
    }
}
