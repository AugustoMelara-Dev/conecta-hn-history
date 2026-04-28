<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Services;

use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SmsOtpNotifier - Infrastructure Implementation
 *
 * Handles the provider call when Twilio credentials are configured and falls
 * back to redacted simulation for local/test environments.
 */
final class SmsOtpNotifier implements OtpNotifierInterface
{
    use LogSanitizer;

    public function __construct(
        private DataScrubberInterface $scrubber,
    ) {}

    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes
    ): void {
        if ($this->isTwilioConfigured()) {
            Http::asForm()
                ->withBasicAuth((string) config('services.twilio.sid'), (string) config('services.twilio.token'))
                ->post(sprintf(
                    'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
                    rawurlencode((string) config('services.twilio.sid'))
                ), [
                    'From' => (string) config('services.twilio.from'),
                    'To' => $identifier,
                    'Body' => "Tu codigo de Conecta HN es {$plainTextCode}. Vence en {$expiryMinutes} minutos.",
                ])
                ->throw();
        }

        Log::info($this->isTwilioConfigured() ? 'SMS OTP enviado' : 'SMS/Email OTP simulado', $this->sanitizePayload([
            'tenant_id' => $tenantId,
            'recipient' => $identifier,
            'expiry' => $expiryMinutes,
        ], $this->scrubber));
    }

    private function isTwilioConfigured(): bool
    {
        return filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.from'));
    }
}
