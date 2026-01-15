<?php

declare(strict_types=1);

namespace App\Domain\Common\Contracts;

/**
 * SnapshotStrategyInterface - The Policy Maker
 * 
 * Decides WHEN a snapshot should be taken, decoupling the 'When' from the 'How'.
 */
interface SnapshotStrategyInterface
{
    /**
     * Determine if a snapshot is required for the given aggregate state.
     * 
     * @param object $aggregate The aggregate instance (usually Versioned).
     */
    public function shouldSnapshot(object $aggregate): bool;
}
