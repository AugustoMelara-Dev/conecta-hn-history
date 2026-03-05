<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\ValueObjects\Payment\PaymentWebhook;

final readonly class PaymentWebhookReceived
{
    public function __construct(
        public PaymentWebhook $webhook
    ) {}
}
