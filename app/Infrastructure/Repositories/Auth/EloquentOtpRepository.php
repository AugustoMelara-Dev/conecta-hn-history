<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Auth;

use App\Domain\Auth\Entities\OtpEntity;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Models\OneTimePassword;
use Carbon\Carbon;

/**
 * EloquentOtpRepository - Infrastructure Adapter for OTP Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DATA MAPPER: Converts Eloquent models to domain entities.              ║
 * ║  HASHING: Model mutator handles hashing - we pass plain code.           ║
 * ║  CONFIG AGNOSTIC: No config() calls - expiry injected via constructor.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentOtpRepository implements OtpRepositoryInterface
{
    public function __construct(
        private readonly int $expiryMinutes // Injected scalar value, configuration agnostic
    ) {}

    /**
     * @inheritDoc
     */
    public function invalidateUserOtps(int $userId, string $type): int
    {
        return OneTimePassword::where('user_id', $userId)
            ->where('type', $type)
            ->delete();
    }

    /**
     * @inheritDoc
     */
    public function invalidateByIdentifier(string $identifier): int
    {
        return OneTimePassword::where('identifier', $identifier)->delete();
    }

    /**
     * @inheritDoc
     */
    public function create(
        ?int $userId,
        string $identifier,
        string $plainCode,
        string $type,
        string $ipAddress,
        string $userAgent,
        int $expiryMinutes = 10
    ): OtpEntity {
        // Use injected expiry from constructor (preferred) or override if explicit argument is passed but default is used
        // Spec says: 'expires_at' => now()->addMinutes($this->expiryMinutes)
        
        // Model mutator (setCodeAttribute) handles hashing automatically
        $model = OneTimePassword::create([
            'user_id' => $userId,
            'identifier' => $identifier,
            'code' => $plainCode, // Plain code - Model will hash it
            'type' => $type ?? 'login',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => now()->addMinutes($this->expiryMinutes),
        ]);

        return OtpEntity::fromModel($model);
    }

    /**
     * @inheritDoc
     */
    public function findValidByIdentifier(string $identifier): ?OtpEntity
    {
        $model = OneTimePassword::where('identifier', $identifier)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return $model ? OtpEntity::fromModel($model) : null;
    }
}
