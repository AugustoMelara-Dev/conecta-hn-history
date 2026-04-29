<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Listeners;

use App\Application\Auth\Contracts\OtpDeliveryPayloadStoreInterface;
use App\Application\Shared\Contracts\CircuitBreakerInterface;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Models\OneTimePassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * SendOtpNotificationListener - Infrastructure Layer Async Worker
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  INFRASTRUCTURE LAYER: ShouldQueue is permitted here (framework edge).  â•‘
 * â•‘  TENANT AWARE: Passes tenantId to notifier for branding/routing.        â•‘
 * â•‘  CIRCUIT BREAKER: Fails fast if external provider is down.              â•‘
 * â•‘  PII PROTECTED: All identifiers masked before logging (GDPR compliant). â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final class SendOtpNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private OtpNotifierInterface $notifier,
        private CircuitBreakerInterface $circuitBreaker,
        private OtpDeliveryPayloadStoreInterface $deliveryPayloadStore,
    ) {}

    /**
     * Handle the event with resilience patterns.
     *
     * @throws Throwable If notification fails (triggering queue retry).
     */
    public function handle(OtpGenerated $event): void
    {
        if ($this->deliveryPayloadStore->wasSent($event->otpId)) {
            Log::info('otp_delivery_skipped_duplicate', [
                'otp_id' => $event->otpId,
                'tenant_id' => $event->tenantId,
            ]);

            return;
        }

        $otp = OneTimePassword::query()
            ->whereKey($event->otpId)
            ->where('tenant_id', $event->tenantId)
            ->first();

        if ($otp === null) {
            Log::warning('OTP notification skipped: OTP record not found', [
                'otp_id' => $event->otpId,
                'tenant_id' => $event->tenantId,
            ]);

            return;
        }

        $deliveryPayload = $this->deliveryPayloadStore->get($event->otpId);

        if ($deliveryPayload === null) {
            Log::info('otp_delivery_skipped_missing_payload', [
                'otp_id' => $event->otpId,
                'tenant_id' => $event->tenantId,
            ]);

            return;
        }

        $serviceName = $otp->type === 'email' ? 'email_provider' : 'sms_provider';
        $maskedIdentifier = $this->maskIdentifier($deliveryPayload['identifier']);

        Log::info('otp_delivery_requested', [
            'identifier' => $maskedIdentifier,
            'tenant_id' => $event->tenantId,
            'channel' => $otp->type,
        ]);

        // 1. FAIL FAST CHECK
        if (! $this->circuitBreaker->isAvailable($serviceName)) {
            Log::warning('otp_delivery_blocked_by_circuit_breaker', [
                'identifier' => $maskedIdentifier,
                'tenant_id' => $event->tenantId,
                'service' => $serviceName,
            ]);

            return; // Abort, do not retry blindly
        }

        try {
            // 2. ATTEMPT OPERATION (Now with tenant context for branding/routing)
            $this->notifier->send(
                recipient: null,
                plainTextCode: $deliveryPayload['plain_text_code'],
                identifier: $deliveryPayload['identifier'],
                tenantId: $event->tenantId,
                expiryMinutes: (int) $deliveryPayload['expiry_minutes'],
                magicUrl: $this->magicUrlFor($otp),
            );

            $this->deliveryPayloadStore->forget($event->otpId);
            $this->deliveryPayloadStore->markSent($event->otpId, (int) $deliveryPayload['expiry_minutes']);

            // 3. REPORT SUCCESS
            $this->circuitBreaker->reportSuccess($serviceName);

            Log::info('otp_delivery_sent', [
                'identifier' => $maskedIdentifier,
                'tenant_id' => $event->tenantId,
                'channel' => $otp->type,
            ]);

        } catch (Throwable $e) {
            // 4. REPORT FAILURE & OBSERVE
            $this->circuitBreaker->reportFailure($serviceName);

            Log::error('otp_delivery_failed', [
                'error' => $e->getMessage(),
                'identifier' => $maskedIdentifier,
                'tenant_id' => $event->tenantId,
                'service' => $serviceName,
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
     * - Email: "user@example.com" â†’ "us***@example.com"
     * - Phone: "+50412345678" â†’ "+504****78"
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

    private function magicUrlFor(OneTimePassword $otp): ?string
    {
        if ($otp->type !== 'email' || ! (bool) config('auth.otp.magic_link_enabled', false)) {
            return null;
        }

        $allowlist = config('auth.otp.magic_link_tenant_allowlist', []);
        if (is_array($allowlist) && $allowlist !== []
            && ! in_array((string) $otp->tenant_id, array_map('strval', $allowlist), true)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'otp.consume',
            now()->addMinutes((int) config('auth.otp.magic_link_expiry_minutes', 10)),
            ['otp' => (string) $otp->getKey()],
        );
    }
}
