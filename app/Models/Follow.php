<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Follow - Social follow relationship (PRO Feature)
 * 
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class Follow extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the user who is following.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
