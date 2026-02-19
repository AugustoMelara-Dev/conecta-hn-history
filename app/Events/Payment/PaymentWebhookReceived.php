<?php

declare(strict_types=1);

namespace App\Events\Payment;

use App\Domain\ValueObjects\Payment\PaymentWebhook;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentWebhook $webhook
    ) {}
}
