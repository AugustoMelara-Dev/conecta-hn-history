<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Campaign - Marketing campaigns (PRO Feature)
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class Campaign extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'subject',
        'content',
        'type',
        'status',
        'scheduled_at',
        'sent_at',
        'recipients_count',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipients_count' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════
}
