<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Resilience;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;
use Throwable;

/**
 * CircuitBreakerDecorator - Resiliency Shield
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FAIL FAST: Prevents cascading failures when providers (Twilio/SES) die.║
 * ║  DISTRIBUTED STATE: Uses Cache (Redis) to sync state across workers.    ║
 * ║  TRANSPARENT: Wraps any OtpNotifierInterface without changing client.   ║
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

    public function send(
        string $otpCode,
        string $recipientIdentifier,
        string $recipientName,
        int $expiryMinutes = 10
    ): void {
        // 1. FAIL FAST CHECK
        if ($this->isOpen()) {
            // Throw specific exception to release worker immediately
            throw new RuntimeException("Circuit Opened: OTP Service [{$this->serviceName}] is currently unavailable.");
        }

        try {
            // 2. DELEGATE EXECUTION
            $this->innerService->send($otpCode, $recipientIdentifier, $recipientName, $expiryMinutes);
            
            // 3. SUCCESS - Reset local failure count if needed (optional optimization)
            // Ideally we don't hit cache on every success to be faster
        } catch (Throwable $e) {
            // 4. ON FAILURE - Record and Trip
            $this->recordFailure();
            throw $e; // Re-throw to ensure job fails
        }
    }

    private function isOpen(): bool
    {
        return $this->cache->has($this->getOpenKey());
    }

    private function recordFailure(): void
    {
        $key = $this->getFailureKey();
        
        // Atomic increment
        $failures = $this->cache->increment($key);
        
        // Set short expiry for the failure window (rolling window approx)
        if ($failures === 1) {
            $this->cache->put($key, 1, 120); // Window of 2 minutes
        }

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->tripCircuit();
        }
    }

    private function tripCircuit(): void
    {
        $this->cache->put($this->getOpenKey(), true, self::OPEN_TTL_SECONDS);
        $this->cache->forget($this->getFailureKey()); // Reset counter
    }

    private function getOpenKey(): string
    {
        return self::CACHE_PREFIX . ":{$this->serviceName}:open";
    }

    private function getFailureKey(): string
    {
        return self::CACHE_PREFIX . ":{$this->serviceName}:failures";
    }
}
