<?php

declare(strict_types=1);

namespace App\Application\Billing\Listeners;

use App\Application\Billing\Contracts\PaymentWebhookSettlementInterface;
use App\Domain\Billing\Events\PaymentWebhookReceived;

final readonly class ProcessPaymentWebhookReceived
{
    public function __construct(
        private PaymentWebhookSettlementInterface $settlement,
    ) {}

    public function handle(PaymentWebhookReceived $event): void
    {
        $this->settlement->settle($event->webhook);
    }
}
