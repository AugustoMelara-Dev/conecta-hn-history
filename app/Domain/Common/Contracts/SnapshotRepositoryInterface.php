<?php

declare(strict_types=1);

namespace App\Domain\Common\Contracts;

use App\Domain\Common\ValueObjects\Snapshot;

/**
 * SnapshotRepositoryInterface - State Compression Contract
 */
interface SnapshotRepositoryInterface
{
    public function save(string $aggregateId, int $version, array $state): void;
    
    public function get(string $aggregateId): ?Snapshot;
}
