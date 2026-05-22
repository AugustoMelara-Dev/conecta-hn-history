<?php

declare(strict_types=1);

namespace App\Application\Billing\DTOs;

final readonly class SubscriptionIntentRecord
{
    public function __construct(
        public string $id,
        public string $status,
        public bool $recovered,
    ) {}
}
