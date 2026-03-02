<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

final readonly class ReconcilePendingSubscriptionsCommand
{
    public function __construct(
        public readonly string $tenantId,
    ) {}
}
