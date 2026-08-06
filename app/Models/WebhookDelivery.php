<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WebhookDelivery - Alias/legacy de AutomationWebhookDelivery.
 */
class WebhookDelivery extends AutomationWebhookDelivery
{
    protected $table = 'automation_webhook_deliveries';
}
