<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Favorite Model - Polymorphic relation
 *
 * Allows users to favorite any model (Tenant, Product, etc.)
 *
 * @property int $id
 * @property string $user_id
 * @property string $favoritable_id
 * @property string $favoritable_type
 */
class Favorite extends Model
{
    protected $fillable = [
        'user_id',
        'favoritable_id',
        'favoritable_type',
    ];

    protected $casts = [
        'user_id' => 'string',
        'favoritable_id' => 'string',
    ];

    /**
     * Get the user who created the favorite
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the favoritable model (Tenant, Product, etc.)
     */
    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }
}
