<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * VerifyOtpCommand - Immutable OTP Verification Credentials
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSPORT ONLY: No validation logic - Handler validates cryptography.  ║
 * ║  SECURITY METADATA: Carries IP for audit and brute-force detection.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class VerifyOtpCommand
{
    public function __construct(
        /** Email or phone number associated with OTP */
        public string $identifier,
        
        /** 6-digit OTP code entered by user (plain text) */
        public string $code,
        
        /** Client IP for security audit and brute-force detection */
        public string $ipAddress,
    ) {}
}
