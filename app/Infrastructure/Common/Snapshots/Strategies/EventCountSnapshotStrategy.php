<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Snapshots\Strategies;

use App\Domain\Common\Contracts\SnapshotStrategyInterface;

/**
 * EventCountSnapshotStrategy - The Accountant
 *
 * Triggers a snapshot every N versions.
 * Configured via constructor for maximum flexibility.
 */
final readonly class EventCountSnapshotStrategy implements SnapshotStrategyInterface
{
    public function __construct(
        private int $threshold
    ) {}

    public function shouldSnapshot(object $aggregate): bool
    {
        // 1. Duck Typing / Interface Check
        // We assume the aggregate has a version() method.
        if (! method_exists($aggregate, 'version')) {
            return false; // Can't decide without version.
        }

        $version = $aggregate->version();

        // 2. The Algorithm (Modulo Logic)
        // Snapshot only if version > 0 (avoid initial state snapshot unless configured otherwise)
        // AND version is a multiple of threshold.
        return $version > 0 && ($version % $this->threshold === 0);
    }
}
