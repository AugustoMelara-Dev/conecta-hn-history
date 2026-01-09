<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * EmailOtpNotifier - Infrastructure Adapter for Email OTP Delivery
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUTHORIZED: This is the ONLY class allowed to use Mail:: facade.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EmailOtpNotifier implements OtpNotifierInterface
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
        Mail::to($recipientIdentifier)->send(new OtpMail($otpCode, $expiryMinutes));

        Log::info('OTP email sent', [
            'identifier' => $this->maskEmail($recipientIdentifier),
        ]);
    }

    /**
     * Mask email for PII-safe logging.
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        return substr($parts[0], 0, 2) . '***@' . $parts[1];
    }
}
