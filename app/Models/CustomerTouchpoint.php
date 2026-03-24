<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTouchpoint extends Model
{
    use BelongsToTenant;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_MANUAL = 'manual';

    protected $fillable = [
        'tenant_id',
        'customer_profile_id',
        'created_by_user_id',
        'channel',
        'direction',
        'automation_key',
        'subject',
        'message',
        'recipient_address',
        'status',
        'opt_in_snapshot',
        'sent_at',
        'failed_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'opt_in_snapshot' => 'boolean',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
