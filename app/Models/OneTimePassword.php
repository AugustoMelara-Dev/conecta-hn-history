<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\AllowsTrustedTenantAssignment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class OneTimePassword extends Model
{
    use AllowsTrustedTenantAssignment, HasFactory, HasUuids;

    // VC-AUDIT-103: Allow only the fields the system needs during OTP creation.
    protected $fillable = [
        'user_id',
        'identifier',
        'code',
        'type',
        'ip_address',
        'user_agent',
        'expires_at',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hash the code before saving - REMOVED TO PREVENT DOUBLE HASHING
     * The Repository handles hashing via HmacOtpHasher.
     */
    // public function setCodeAttribute($value): void ...

    /**
     * Verify if the OTP is still valid
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Verify the code against the hashed value
     *
     * @deprecated Use OtpHasherInterface::verify() instead.
     */
    // public function verifyCode(string $code): bool ...

    /**
     * Scope to get only valid (non-expired) OTPs
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get OTPs for a specific identifier
     */
    public function scopeForIdentifier($query, string $identifier)
    {
        return $query->where('identifier', $identifier);
    }
}
