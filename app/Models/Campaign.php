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
        'tenant_id',
        'subject',
        'content',
        'type',
        'audience_type',
        'status',
        'scheduled_at',
        'sent_at',
        'recipients_count',
    ];

    /**
     * SECURITY: Immutable lifecycle fields.
     * tenant_id is assigned from tenant context and may also be injected by
     * Filament creation flows, so it must remain mass-assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'audience_type' => \App\Domain\Marketing\Enums\AudienceType::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipients_count' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════
}
