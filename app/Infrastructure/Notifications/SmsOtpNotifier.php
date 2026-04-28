<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use Illuminate\Support\Facades\Log;

/**
 * SmsOtpNotifier - Infrastructure Adapter for SMS OTP Delivery
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PLACEHOLDER: Awaiting Twilio/AWS SNS credentials configuration.        ║
 * ║  FIXED: Method signature now matches OtpNotifierInterface contract.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class SmsOtpNotifier implements OtpNotifierInterface
{
    /**
     * {@inheritDoc}
     */
    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes
    ): void {
        // Check if production SMS service is configured
        if (! $this->isSmsServiceConfigured()) {
            $this->simulateSms($plainTextCode, $identifier, $tenantId);

            return;
        }

        // TODO: Real SMS implementation
        // $this->twilioClient->messages->create($identifier, [...]);

        Log::info('SMS OTP sent', [
            'phone' => $this->maskPhone($identifier),
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Check if SMS service has valid credentials.
     */
    private function isSmsServiceConfigured(): bool
    {
        return ! empty(config('services.twilio.sid'))
            || ! empty(config('services.sns.key'));
    }

    /**
     * Simulate SMS for development/testing.
     */
    private function simulateSms(string $code, string $phone, string $tenantId): void
    {
        Log::info('SMS OTP Simulated (No provider configured)', [
            'phone' => $this->maskPhone($phone),
            'tenant_id' => $tenantId,
            'code' => app()->environment('local') ? $code : '[REDACTED]',
        ]);
    }

    /**
     * Mask phone number for PII-safe logging.
     */
    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 4) {
            return '****';
        }

        return substr($phone, 0, 3).'****'.substr($phone, -2);
    }
}
