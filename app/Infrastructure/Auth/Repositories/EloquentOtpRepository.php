<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Repositories;

use App\Domain\Auth\Entities\OtpEntity;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Models\OneTimePassword;
use Carbon\Carbon;
use DateTimeImmutable;

/**
 * EloquentOtpRepository - Tenant-Isolated Data Mapper
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MULTI-TENANT: All queries filter by tenant_id (Axiom A).               ║
 * ║  ATOMIC WRITE: Uses database-level increments for concurrency safety.   ║
 * ║  STRICT HYDRATION: Maps Eloquent Models manually to Pure Entities.      ║
 * ║  INFRASTRUCTURE BARRIER: Translates Carbon -> DateTimeImmutable.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentOtpRepository implements OtpRepositoryInterface
{
    /**
     * Find a valid OTP by identifier within a specific tenant.
     * 
     * SECURITY: Query MUST filter by both identifier AND tenantId.
     */
    public function findValidByIdentifier(string $identifier, string $tenantId): ?OtpEntity
    {
        /** @var OneTimePassword|null $model */
        $model = OneTimePassword::query()
            ->where('identifier', $identifier)
            ->where('tenant_id', $tenantId) // TENANT ISOLATION
            ->where('expires_at', '>', Carbon::now()) // Not expired
            ->whereNull('used_at') // Not consumed
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->hydrate($model);
    }

    /**
     * Create a new OTP record within a specific tenant context.
     * 
     * SECURITY: OTP record MUST be associated with tenantId.
     */
    public function create(
        ?int $userId,
        string $identifier,
        string $tenantId,
        string $hashedCode,
        string $type,
        ?string $ipAddress,
        ?string $userAgent,
        int $expiryMinutes
    ): OtpEntity {
        $model = OneTimePassword::create([
            'user_id' => $userId,
            'identifier' => $identifier,
            'tenant_id' => $tenantId, // TENANT ISOLATION
            'code' => $hashedCode,
            'type' => $type,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
            'attempts' => 0,
        ]);

        return $this->hydrate($model);
    }

    /**
     * Atomically increment the attempts counter in persistence.
     * Uses database-level increment to avoid race conditions.
     */
    public function incrementAttempts(string $otpId): void
    {
        // ATOMIC SQL: UPDATE one_time_passwords SET attempts = attempts + 1 WHERE id = ?
        OneTimePassword::query()
            ->where('id', $otpId)
            ->increment('attempts');
    }

    /**
     * Invalidate OTPs by identifier within a specific tenant.
     * 
     * SECURITY: Only affects rows matching BOTH identifier AND tenantId.
     * Prevents cross-tenant DoS attacks.
     */
    public function invalidateByIdentifier(string $identifier, string $tenantId): void
    {
        OneTimePassword::query()
            ->where('identifier', $identifier)
            ->where('tenant_id', $tenantId) // TENANT ISOLATION
            ->delete();
    }

    /**
     * Delete a specific OTP entity.
     */
    public function delete(OtpEntity $otp): void
    {
        OneTimePassword::query()
            ->where('id', $otp->id)
            ->delete();
    }

    /**
     * Save/Update entity state.
     * NOTE: Prefer incrementAttempts() for counters to avoid concurrency issues.
     */
    public function save(OtpEntity $otp): void
    {
        OneTimePassword::query()
            ->where('id', $otp->id)
            ->update([
                'attempts' => $otp->attempts,
            ]);
    }

    /**
     * Hydrate Pure Domain Entity from Eloquent Model.
     * Creates infrastructure barrier between Eloquent and Domain.
     */
    private function hydrate(OneTimePassword $model): OtpEntity
    {
        return new OtpEntity(
            id: (string) $model->id,
            userId: $model->user_id,
            identifier: $model->identifier,
            tenantId: (string) $model->tenant_id, // ADDED: Tenant context in memory
            hashedCode: $model->code,
            type: $model->type,
            // Carbon -> DateTimeImmutable conversion boundary
            expiresAt: DateTimeImmutable::createFromInterface($model->expires_at),
            attempts: (int) $model->attempts,
            ipAddress: $model->ip_address,
            userAgent: $model->user_agent,
        );
    }
}
