<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

use App\Domain\Identity\Entities\UserEntity;

/**
 * OtpNotifierInterface - Tenant-Aware Domain Port for OTP Delivery
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MULTI-TENANT: Requires tenantId via UserEntity or explicit context.    ║
 * ║  PURE DOMAIN: No Illuminate\Mail dependencies.                          ║
 * ║  SECRET HANDOFF: This interface accepts the plain text secret code.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface OtpNotifierInterface
{
    /**
     * Send OTP code to recipient.
     *
     * @param  UserEntity|null  $recipient  User entity if available (Login/Forgot Password). Null for new registrations.
     * @param  string  $plainTextCode  The secret code.
     * @param  string  $identifier  Fallback identifier (email/phone) if user entity is null.
     * @param  string  $tenantId  Tenant context.
     * @param  int  $expiryMinutes  Time until code expires.
     * @param  string|null  $magicUrl  Signed link that consumes the OTP on the current device.
     */
    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes,
        ?string $magicUrl = null
    ): void;
}
