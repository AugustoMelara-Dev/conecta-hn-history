<?php

declare(strict_types=1);

namespace App\Application\Billing\Contracts;

use App\Domain\ValueObjects\Payment\PaymentWebhook;

interface PaymentWebhookSettlementInterface
{
    public function settle(PaymentWebhook $webhook): void;
}
