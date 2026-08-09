<?php

declare(strict_types=1);

namespace App\Models;

/**
 * WebhookDelivery - Alias/legacy de AutomationWebhookDelivery.
 */
class WebhookDelivery extends AutomationWebhookDelivery
{
    protected $table = 'automation_webhook_deliveries';
}
