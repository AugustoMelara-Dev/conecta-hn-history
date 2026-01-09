<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

/**
 * OtpNotifierInterface - Domain Port for OTP Delivery
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN: No Illuminate\Mail or third-party dependencies.           ║
 * ║  POLYMORPHIC: Implementations can be Email, SMS, Push, etc.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface OtpNotifierInterface
{
    /**
     * Send OTP code to recipient.
     *
     * @param string $otpCode The generated OTP code
     * @param string $recipientIdentifier Email or phone number
     * @param string $recipientName User's display name for personalization
     * @param int $expiryMinutes Expiration time for user message
     * @return void
     */
    public function send(
        string $otpCode,
        string $recipientIdentifier,
        string $recipientName,
        int $expiryMinutes = 10
    ): void;
}
