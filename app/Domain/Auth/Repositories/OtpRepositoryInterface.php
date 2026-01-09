<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Entities\OtpEntity;

/**
 * OtpRepositoryInterface - Pure Domain Port for OTP Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: Returns domain entities, not Eloquent models.                    ║
 * ║  INVERSED: Domain depends on abstraction, not infrastructure.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface OtpRepositoryInterface
{
    /**
     * Invalidate all previous OTPs for a user and type.
     *
     * @param int $userId
     * @param string $type OTP purpose (login, password_reset, etc.)
     * @return int Number of invalidated records
     */
    public function invalidateUserOtps(int $userId, string $type): int;

    /**
     * Invalidate all previous OTPs for an identifier (email/phone).
     *
     * @param string $identifier Email or phone
     * @return int Number of invalidated records
     */
    public function invalidateByIdentifier(string $identifier): int;

    /**
     * Create a new OTP record.
     *
     * @param int|null $userId User ID (null for new registrations)
     * @param string $identifier Email or phone
     * @param string $plainCode Plain OTP code (hashing handled by infrastructure)
     * @param string $type OTP purpose
     * @param string $ipAddress Client IP
     * @param string $userAgent Client User-Agent
     * @param int $expiryMinutes Minutes until expiration
     * @return OtpEntity Created OTP entity
     */
    public function create(
        ?int $userId,
        string $identifier,
        string $plainCode,
        string $type,
        string $ipAddress,
        string $userAgent,
        int $expiryMinutes = 10
    ): OtpEntity;

    /**
     * Find valid OTP by identifier.
     *
     * @param string $identifier
     * @return OtpEntity|null
     */
    public function findValidByIdentifier(string $identifier): ?OtpEntity;
}
