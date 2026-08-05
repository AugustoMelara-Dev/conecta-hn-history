<?php

declare(strict_types=1);

namespace App\Application\Orders\Commands;

final readonly class TransitionOrderCommand
{
    public function __construct(
        public string $tenantId,
        public ?string $actorId,
        public int $orderId,
        public string $targetStatus,
        public ?string $reason = null,
        public bool $systemInitiated = false,
    ) {}
}
