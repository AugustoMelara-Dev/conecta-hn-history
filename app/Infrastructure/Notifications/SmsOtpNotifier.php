<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use Illuminate\Support\Facades\Log;

/**
 * SmsOtpNotifier - Infrastructure Adapter for SMS OTP Delivery
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PLACEHOLDER: Awaiting Twilio/AWS SNS credentials configuration.        ║
 * ║  Currently simulates SMS delivery via logging.                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * TODO: Integrate with production SMS provider (Twilio/AWS SNS).
 */
final class SmsOtpNotifier implements OtpNotifierInterface
{
    /**
     * @inheritDoc
     */
    public function send(
        string $otpCode,
        string $recipientIdentifier,
        string $recipientName,
        int $expiryMinutes = 10
    ): void {
        // Check if production SMS service is configured
        if (!$this->isSmsServiceConfigured()) {
            $this->simulateSms($otpCode, $recipientIdentifier);
            return;
        }

        // TODO: Real SMS implementation
        // $this->twilioClient->messages->create($recipientIdentifier, [...]);
        
        Log::info('SMS OTP sent', [
            'phone' => $this->maskPhone($recipientIdentifier),
        ]);
    }

    /**
     * Check if SMS service has valid credentials.
     */
    private function isSmsServiceConfigured(): bool
    {
        return !empty(config('services.twilio.sid')) 
            || !empty(config('services.sns.key'));
    }

    /**
     * Simulate SMS for development/testing.
     */
    private function simulateSms(string $code, string $phone): void
    {
        Log::info('SMS OTP Simulated (No provider configured)', [
            'phone' => $this->maskPhone($phone),
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
        return substr($phone, 0, 3) . '****' . substr($phone, -2);
    }
}
