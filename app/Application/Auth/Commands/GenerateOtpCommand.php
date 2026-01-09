<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * GenerateOtpCommand - Immutable Input Contract for OTP Generation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY METADATA: Carries IP and User-Agent for audit and rate limit. ║
 * ║  TRANSPORT ONLY: No validation logic - Handler validates.               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class GenerateOtpCommand
{
    public function __construct(
        /** Email or phone number (raw input) */
        public string $identifier,
        
        /** Client IP address (for rate limiting and audit) */
        public string $ipAddress,
        
        /** Client User-Agent (for device fingerprinting) */
        public string $userAgent,
        
        /** OTP purpose: 'login', 'password_reset', 'verify_contact' */
        public string $type = 'login',
    ) {}
}
