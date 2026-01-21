<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * CriticalConcurrencyBreachEvent - Level 51.1 Zenith True
 *
 * Fired immediately before SIGTERM when a Split-Brain scenario is detected.
 * This event allows monitoring systems to capture the breach before process death.
 */
final class CriticalConcurrencyBreachEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $expectedWorkerId,
        public readonly ?string $actualWorkerId,
        public readonly string $messageId
    ) {}
}
