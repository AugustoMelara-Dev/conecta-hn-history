<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Entities\OtpEntity;

/**
 * OtpRepositoryInterface - Tenant-Aware Data Mapper Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MULTI-TENANT: All methods require explicit tenantId (Axiom A).         ║
 * ║  PURE INPUT/OUTPUT: Operates strictly on OtpEntity.                     ║
 * ║  ATOMIC OPERATIONS: Enforces atomicity for counters (incrementAttempts).║
 * ║  PERSISTENCE IGNORANT: No references to Eloquent/SQL.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface OtpRepositoryInterface
{
    /**
     * Find a valid (not expired, exists) OTP by identifier and tenant.
     * Hydrates and returns a pure OtpEntity.
     *
     * SECURITY: Query MUST filter by both identifier AND tenantId
     * to prevent cross-tenant OTP verification attacks.
     */
    public function findValidByIdentifier(string $identifier, string $tenantId): ?OtpEntity;

    /**
     * Create a new OTP record within a specific tenant context.
     *
     * SECURITY: OTP record MUST be associated with tenantId
     * to ensure tenant isolation in persistence layer.
     */
    public function create(
        ?string $userId,
        string $identifier,
        string $tenantId,
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
     * @param  string  $otpId  The unique entity ID
     */
    public function incrementAttempts(string $otpId): void;

    /**
     * Atomically increment attempts with conditional gatekeeping.
     *
     * @param  string  $otpId  Unique Entity ID
     * @param  int  $maxAttempts  Threshold
     *
     * @throws \App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException
     */
    public function incrementAttemptsAtomically(string $otpId, int $maxAttempts): void;

    /**
     * Invalidate/Delete OTP by identifier within a specific tenant.
     *
     * SECURITY: Invalidation MUST be scoped by tenantId to prevent
     * cross-tenant DoS attacks where attacker invalidates victim's OTPs.
     */
    public function invalidateByIdentifier(string $identifier, string $tenantId): void;

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
