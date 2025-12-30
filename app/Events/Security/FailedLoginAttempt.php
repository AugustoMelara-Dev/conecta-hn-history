<?php

declare(strict_types=1);

namespace App\Events\Security;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FailedLoginAttempt - Security Audit Event for Brute Force Detection
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY AUDIT: Failed Authentication Tracking                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  LISTENERS CAN:                                                         ║
 * ║    - Log to security_logs table                                         ║
 * ║    - Increment rate limiter counter                                     ║
 * ║    - Trigger CAPTCHA after N attempts                                   ║
 * ║    - Block IP after threshold exceeded                                  ║
 * ║    - Send alert to security team                                        ║
 * ║                                                                          ║
 * ║  FORENSIC DATA:                                                         ║
 * ║    - email: attempted username/email                                    ║
 * ║    - ip_address: origin IP for geo-blocking                            ║
 * ║    - user_agent: browser fingerprinting                                ║
 * ║    - tenant_slug: if attempt was on tenant subdomain                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class FailedLoginAttempt
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly ?string $tenantSlug = null,
        public readonly ?string $failureReason = null,
    ) {}

    /**
     * Get the attempt fingerprint for rate limiting.
     */
    public function getFingerprint(): string
    {
        return md5($this->email . '|' . $this->ipAddress);
    }

    /**
     * Convert to array for logging.
     */
    public function toAuditLog(): array
    {
        return [
            'event' => 'failed_login_attempt',
            'email' => $this->email,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'tenant_slug' => $this->tenantSlug,
            'failure_reason' => $this->failureReason,
            'fingerprint' => $this->getFingerprint(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
