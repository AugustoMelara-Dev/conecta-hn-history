<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class OneTimePassword extends Model
{
    use HasFactory, HasUuids;

    // VC-AUDIT-103: Hardened to prevent mass assignment of sensitive fields
    protected $guarded = ['id', 'user_id', 'created_at', 'updated_at'];

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
