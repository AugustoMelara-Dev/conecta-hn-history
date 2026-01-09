<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entities;

use App\Models\OneTimePassword;
use Carbon\Carbon;

/**
 * OtpEntity - Pure Domain Entity for OTP
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: No Eloquent methods (save/delete/update) exposed.                ║
 * ║  IMMUTABLE: Readonly properties, no setters.                            ║
 * ║  HYDRATION: Static factory from Eloquent model.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class OtpEntity
{
    public function __construct(
        public string $id,
        public ?int $userId,
        public string $identifier,
        public string $code, // Changed from hashedCode to code as per spec
        public string $type,
        public Carbon $expiresAt,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    /**
     * Hydrate entity from Eloquent model.
     *
     * SINGLE COUPLING POINT: This is the only place where Eloquent is referenced.
     */
    public static function fromModel(OneTimePassword $model): self
    {
        return new self(
            id: (string) $model->id,
            userId: $model->user_id,
            identifier: $model->identifier,
            code: $model->code, // Can be hash or plain depending on context, usually hash from DB
            type: $model->type,
            expiresAt: Carbon::parse($model->expires_at),
            ipAddress: $model->ip_address,
            userAgent: $model->user_agent,
        );
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    /**
     * Verify code against hashed value.
     */
    public function verifyCode(string $plainCode): bool
    {
        return password_verify($plainCode, $this->code);
    }
}
