<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class OneTimePassword extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

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
     * Hash the code before saving
     */
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = Hash::make($value);
    }

    /**
     * Verify if the OTP is still valid
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Verify the code against the hashed value
     */
    public function verifyCode(string $code): bool
    {
        return Hash::check($code, $this->code);
    }

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
