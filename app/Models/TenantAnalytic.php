<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TenantAnalytic - Aggregated analytics data per tenant per day
 *
 * SECURITY: Uses BelongsToTenant trait for automatic tenant isolation
 */
class TenantAnalytic extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'date',
        'unique_visits',
        'total_visits',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'date' => 'date',
        'unique_visits' => 'integer',
        'total_visits' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════
}
