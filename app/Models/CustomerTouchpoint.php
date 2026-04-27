<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        'campaign_id',
        'campaign_run_key',
        'customer_conversation_id',
        'created_by_user_id',
        'channel',
        'direction',
        'automation_key',
        'template_key',
        'subject',
        'message',
        'recipient_address',
        'status',
        'provider',
        'provider_message_id',
        'delivery_status',
        'attempt_count',
        'retry_limit',
        'opt_in_snapshot',
        'sent_at',
        'last_attempt_at',
        'next_retry_at',
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
        'attempt_count' => 'integer',
        'retry_limit' => 'integer',
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CustomerConversation::class, 'customer_conversation_id');
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
            self::DELIVERY_READ => 'Leído',
            self::DELIVERY_BOUNCED => 'Rebotado',
            self::DELIVERY_COMPLAINED => 'Queja',
            self::DELIVERY_REPLIED => 'Respondido',
            self::DELIVERY_DELAYED => 'Retrasado',
            self::DELIVERY_FAILED => 'Fallido',
            default => 'Enviado',
        };
    }

    public function getTemplateLabelAttribute(): string
    {
        $value = $this->template_key ?: $this->automation_key;

        return filled($value)
            ? str((string) $value)->replace('_', ' ')->replace('-', ' ')->title()->value()
            : 'Manual';
    }

    public function getAttemptsSummaryAttribute(): string
    {
        $maxAttempts = max(1, ((int) $this->retry_limit) + 1);
        $attempts = max(0, (int) $this->attempt_count);

        return "{$attempts}/{$maxAttempts}";
    }

    public function getRemainingRetriesAttribute(): int
    {
        $retriesUsed = max(0, ((int) $this->attempt_count) - 1);

        return max(0, ((int) $this->retry_limit) - $retriesUsed);
    }

    public function isRetryable(?Carbon $moment = null): bool
    {
        $moment ??= now();

        if ($this->remaining_retries <= 0) {
            return false;
        }

        $retryableDelivery = in_array($this->delivery_status, [
            self::DELIVERY_FAILED,
            self::DELIVERY_BOUNCED,
            self::DELIVERY_DELAYED,
        ], true);

        if (! $retryableDelivery && ! ($this->status === self::STATUS_FAILED && blank($this->delivery_status))) {
            return false;
        }

        if ($this->next_retry_at instanceof Carbon) {
            return $this->next_retry_at->lessThanOrEqualTo($moment);
        }

        return $retryableDelivery || $this->status === self::STATUS_FAILED;
    }
}
