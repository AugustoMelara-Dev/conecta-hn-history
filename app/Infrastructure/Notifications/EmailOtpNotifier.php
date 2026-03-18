<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * EmailOtpNotifier - Infrastructure Adapter for Email OTP Delivery
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUTHORIZED: This is the ONLY class allowed to use Mail:: facade.       ║
 * ║  FIXED: Method signature now matches OtpNotifierInterface contract.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EmailOtpNotifier implements OtpNotifierInterface
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
        // Use recipient email if available, otherwise use the provided identifier
        $recipientEmail = $recipient !== null ? $recipient->email() : $identifier;

        Mail::to($recipientEmail)->send(new OtpMail($plainTextCode, $expiryMinutes));

        Log::info('OTP email sent', [
            'identifier' => $this->maskEmail($recipientEmail),
            'tenant_id' => $tenantId,
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

        return substr($parts[0], 0, 2).'***@'.$parts[1];
    }
}
