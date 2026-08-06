<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AutomationWebhookDelivery - Delivery de webhook enviado.
 */
class AutomationWebhookDelivery extends Model
{
    use HasUuids;

    protected $table = 'automation_webhook_deliveries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'webhook_id',
        'event',
        'payload',
        'status_code',
        'attempts',
        'response',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'attempts' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
