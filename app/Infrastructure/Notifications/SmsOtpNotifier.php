<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use Illuminate\Support\Facades\Http;
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
        int $expiryMinutes,
        ?string $magicUrl = null
    ): void {
        // Check if production SMS service is configured
        if (! $this->isSmsServiceConfigured()) {
            $this->simulateSms($plainTextCode, $identifier, $tenantId);

            return;
        }

        $response = Http::asForm()
            ->withBasicAuth((string) config('services.twilio.sid'), (string) config('services.twilio.token'))
            ->post(sprintf(
                'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
                rawurlencode((string) config('services.twilio.sid'))
            ), [
                'From' => (string) config('services.twilio.from'),
                'To' => $identifier,
                'Body' => "Tu código de Vito Business OS es {$plainTextCode}. Vence en {$expiryMinutes} minutos.",
            ]);

        $response->throw();

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
            && ! empty(config('services.twilio.token'))
            && ! empty(config('services.twilio.from'));
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
