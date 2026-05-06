<?php

declare(strict_types=1);

namespace App\Domain\Interactions\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class InteractionRecorded
{
    public function __construct(
        public int $interactionId,
        public int $tenantId,
    ) {}
}
