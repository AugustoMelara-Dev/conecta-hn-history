<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts\Payment;

use App\Domain\ValueObjects\Payment\PaymentWebhook;

interface PaymentWebhookHandlerInterface
{
    /**
     * Handles the webhook logic and returns a standardized response.
     */
    public function handle(PaymentWebhook $webhook): array;
}
