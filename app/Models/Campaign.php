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
        'title',
        'subject',
        'content',
        'type',
        'status',
        'scheduled_at',
        'sent_at',
        'recipients_count',
        'delivered_count',
        'failed_count',
        'blocked_count',
        'dispatch_started_at',
        'completed_at',
        'failure_reason',
    ];

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'dispatch_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'recipients_count' => 'integer',
        'delivered_count' => 'integer',
        'failed_count' => 'integer',
        'blocked_count' => 'integer',
    ];

    public function isDispatchable(): bool
    {
        return $this->type === 'email'
            && in_array($this->status, ['draft', 'paused', 'scheduled'], true)
            && trim((string) $this->subject) !== ''
            && trim((string) $this->content) !== '';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════
}
