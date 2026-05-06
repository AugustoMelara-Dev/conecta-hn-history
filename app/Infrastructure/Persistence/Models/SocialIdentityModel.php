<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Models\User as UserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SocialIdentityModel (Infrastructure Persistence Model)
 *
 * Mapped to 'social_identities' table.
 */
class SocialIdentityModel extends Model
{
    use HasFactory;

    protected $table = 'social_identities';

    protected $fillable = [
        'user_id',
        'provider_name',
        'provider_id',
        'token',
        'avatar',
    ];

    /**
     * Relationship: Identity belongs to User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }
}
