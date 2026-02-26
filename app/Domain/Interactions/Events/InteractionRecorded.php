<?php

declare(strict_types=1);

namespace App\Domain\Interactions\Events;

final readonly class InteractionRecorded
{
    public function __construct(
        public string $interactionId,
        public string $tenantId,
    ) {}
}
