<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Repositories;

use App\Domain\Auth\Entities\OtpEntity;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Models\OneTimePassword;
use Carbon\Carbon;
use DateTimeImmutable;

/**
 * EloquentOtpRepository - Transactional Data Mapper
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMIC WRITE: Uses database-level increments for concurrency safety.   ║
 * ║  STRICT HYDRATION: Maps Eloquent Models manually to Pure Entities.      ║
 * ║  INFRASTRUCTURE BARRIER: Translates Carbon -> DateTimeImmutable.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentOtpRepository implements OtpRepositoryInterface
{
    public function findValidByIdentifier(string $identifier): ?OtpEntity
    {
        /** @var OneTimePassword|null $model */
        $model = OneTimePassword::query()
            ->where('identifier', $identifier)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->hydrate($model);
    }

    public function create(
        ?int $userId,
        string $identifier,
        string $hashedCode,
        string $type,
        ?string $ipAddress,
        ?string $userAgent,
        int $expiryMinutes
    ): OtpEntity {
        $model = OneTimePassword::create([
            'user_id' => $userId,
            'identifier' => $identifier,
            'code' => $hashedCode, 
            'type' => $type,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
            'attempts' => 0,
        ]);

        return $this->hydrate($model);
    }

    public function incrementAttempts(string $otpId): void
    {
        // ATOMIC SQL: UPDATE one_time_passwords SET attempts = attempts + 1 WHERE id = ?
        OneTimePassword::query()
            ->where('id', $otpId)
            ->increment('attempts');
    }

    public function invalidateByIdentifier(string $identifier): void
    {
        OneTimePassword::query()
            ->where('identifier', $identifier)
            ->delete();
    }

    public function delete(OtpEntity $otp): void
    {
        OneTimePassword::query()
            ->where('id', $otp->id)
            ->delete();
    }

    public function save(OtpEntity $otp): void
    {
        OneTimePassword::query()
            ->where('id', $otp->id)
            ->update([
                'attempts' => $otp->attempts,
                // Add other mutable fields if any
            ]);
    }

    /**
     * Hydrate Pure Domain Entity from Eloquent Model.
     */
    private function hydrate(OneTimePassword $model): OtpEntity
    {
        return new OtpEntity(
            id: (string) $model->id,
            userId: $model->user_id,
            identifier: $model->identifier,
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
