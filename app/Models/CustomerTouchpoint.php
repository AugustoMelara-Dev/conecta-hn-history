<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTouchpoint extends Model
{
    use BelongsToTenant;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_MANUAL = 'manual';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_OPENED = 'opened';

    public const DELIVERY_CLICKED = 'clicked';

    public const DELIVERY_READ = 'read';

    public const DELIVERY_BOUNCED = 'bounced';

    public const DELIVERY_COMPLAINED = 'complained';

    public const DELIVERY_REPLIED = 'replied';

    public const DELIVERY_DELAYED = 'delayed';

    public const DELIVERY_FAILED = 'failed';

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
        'provider',
        'provider_message_id',
        'delivery_status',
        'opt_in_snapshot',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'complained_at',
        'replied_at',
        'failed_at',
        'last_error',
        'provider_payload',
        'metadata',
    ];

    protected $casts = [
        'opt_in_snapshot' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'complained_at' => 'datetime',
        'replied_at' => 'datetime',
        'failed_at' => 'datetime',
        'provider_payload' => 'array',
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

    public function events(): HasMany
    {
        return $this->hasMany(CustomerTouchpointEvent::class)->latest('occurred_at');
    }

    public function getDeliveryStatusLabelAttribute(): string
    {
        return match ($this->delivery_status) {
            self::DELIVERY_DELIVERED => 'Entregado',
            self::DELIVERY_OPENED => 'Abierto',
            self::DELIVERY_CLICKED => 'Con clic',
            self::DELIVERY_READ => 'Leido',
            self::DELIVERY_BOUNCED => 'Rebotado',
            self::DELIVERY_COMPLAINED => 'Queja',
            self::DELIVERY_REPLIED => 'Respondido',
            self::DELIVERY_DELAYED => 'Retrasado',
            self::DELIVERY_FAILED => 'Fallido',
            default => 'Enviado',
        };
    }
}
