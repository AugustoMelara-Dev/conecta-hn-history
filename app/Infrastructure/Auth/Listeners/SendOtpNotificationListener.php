<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Listeners;

use App\Application\Common\Contracts\CircuitBreakerInterface;
use App\Application\Shared\Contracts\SensitivePayloadCipherInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SendOtpNotificationListener - Infrastructure Layer Async Worker
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE LAYER: ShouldQueue is permitted here (framework edge).  ║
 * ║  TENANT AWARE: Passes tenantId to notifier for branding/routing.        ║
 * ║  CIRCUIT BREAKER: Fails fast if external provider is down.              ║
 * ║  PII PROTECTED: All identifiers masked before logging (GDPR compliant). ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class SendOtpNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;
    use LogSanitizer;

    public function __construct(
        private OtpNotifierInterface $notifier,
        private CircuitBreakerInterface $circuitBreaker,
        private SensitivePayloadCipherInterface $payloadCipher,
    ) {}

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(OtpGenerated $event): array
    {
        return [new TenantQueueMiddleware($event->tenantId)];
    }

    /**
     * Handle the event with resilience patterns.
     *
     * @throws Throwable If notification fails (triggering queue retry).
     */
    public function handle(OtpGenerated $event): void
    {
        if ($event->encryptedCode === null || $event->identifier === null) {
            throw new \RuntimeException('OtpGenerated delivery payload is incomplete.');
        }

        $serviceName = 'sms_provider';
        $maskedIdentifier = $this->maskIdentifier($event->identifier);

        // 1. FAIL FAST CHECK
        if (! $this->circuitBreaker->isAvailable($serviceName)) {
            Log::warning('Circuit Open: Skipping SMS send', [
                'identifier' => $maskedIdentifier,
                'tenant_id' => $event->tenantId,
            ]);

            return; // Abort, do not retry blindly
        }

        try {
            // 2. ATTEMPT OPERATION (Now with tenant context for branding/routing)
            $this->notifier->send(
                recipient: null,
                plainTextCode: $this->payloadCipher->decrypt($event->encryptedCode),
                identifier: $event->identifier,
                tenantId: $event->tenantId,
                expiryMinutes: $event->expiryMinutes
            );

            // 3. REPORT SUCCESS
            $this->circuitBreaker->reportSuccess($serviceName);

            Log::info('OTP notification sent successfully', [
                'identifier' => $maskedIdentifier,
                'tenant_id' => $event->tenantId,
            ]);

        } catch (Throwable $e) {
            // 4. REPORT FAILURE & OBSERVE
            $this->circuitBreaker->reportFailure($serviceName);

            Log::error('SMS Send Failed', [
                'error' => $e->getMessage(),
                'identifier' => $maskedIdentifier,
                'tenant_id' => $event->tenantId,
            ]);

            // 5. RETRY STRATEGY (Let queue handle it via backoff)
            throw $e;
        }
    }

    /**
     * Mask PII identifier for GDPR-compliant logging.
     *
     * Examples:
     *
     * - Email: "user@example.com" → "us***@example.com"
     * - Phone: "+50412345678" → "+504****78"
     */
    private function maskIdentifier(string $identifier): string
    {
        // Email masking
        if (str_contains($identifier, '@')) {
            $parts = explode('@', $identifier);
            $local = $parts[0];
            $domain = $parts[1] ?? '';

            $visibleChars = min(2, strlen($local));
            $masked = substr($local, 0, $visibleChars).'***';

            return $masked.'@'.$domain;
        }

        // Phone masking (show first 4 and last 2 characters)
        $length = strlen($identifier);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($identifier, 0, 4).'****'.substr($identifier, -2);
    }
}
