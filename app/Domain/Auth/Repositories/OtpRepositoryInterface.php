<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Entities\OtpEntity;

/**
 * OtpRepositoryInterface - Strict Data Mapper Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE INPUT/OUTPUT: Operates strictly on OtpEntity.                     ║
 * ║  ATOMIC OPERATIONS: Enforces atomicity for counters (incrementAttempts).║
 * ║  PERSISTENCE IGNORANT: No references to Eloquent/SQL.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface OtpRepositoryInterface
{
    /**
     * Find a valid (not expired, exists) OTP by identifier string.
     * Hydrates and returns a pure OtpEntity.
     */
    public function findValidByIdentifier(string $identifier): ?OtpEntity;

    /**
     * Create a new OTP record.
     */
    public function create(
        ?int $userId,
        string $identifier,
        string $hashedCode,
        string $type,
        ?string $ipAddress,
        ?string $userAgent,
        int $expiryMinutes
    ): OtpEntity;

    /**
     * Atomically increment the attempts counter in persistence.
     * This moves the "race condition" risk from PHP memory to DB locking.
     * 
     * @param string $otpId The unique entity ID
     */
    public function incrementAttempts(string $otpId): void;

    /**
     * Invalidate/Delete OTP by identifier (Clean up).
     */
    public function invalidateByIdentifier(string $identifier): void;

    /**
     * Delete a specific OTP entity.
     */
    public function delete(OtpEntity $otp): void;
    
    /**
     * Save/Update entity state (General purpose).
     * NOTE: Prefer incrementAttempts() for counters to avoid concurrency issues.
     */
    public function save(OtpEntity $otp): void;
}
