<?php

declare(strict_types=1);

namespace App\Application\Billing\Contracts;

use App\Application\Billing\DTOs\SubscriptionIntentRecord;

interface SubscriptionIntentStoreInterface
{
    public function createOrRecoverPending(
        string $intentId,
        string $tenantId,
        string $clientIdempotencyKey,
        string $planIdentifier,
        string $gatewayProvider,
        string $pendingStatus,
    ): SubscriptionIntentRecord;

    public function markFailed(string $intentId, ?string $reason): void;

    public function markCompleted(string $intentId, ?string $gatewayTransactionId): void;
}
