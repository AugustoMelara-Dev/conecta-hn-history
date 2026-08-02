<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

final readonly class BuyerActionRequest
{
    public function __construct(
        public string $projectionId,
        public string $actionType,
        public int $quantity = 1,
        public ?string $desiredLocalDate = null,
    ) {}
}
