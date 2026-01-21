<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use Illuminate\Support\Str;

/**
 * RamseyUuidGenerator - Laravel UUID Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE ADAPTER: Contains framework-specific UUID logic.        ║
 * ║  ORDERED UUID: Uses orderedUuid() for better database index performance.║
 * ║  IMMUTABLE: final readonly ensures no state mutation.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RamseyUuidGenerator implements UuidGeneratorInterface
{
    /**
     * Generate a new ordered UUID v4.
     *
     * Uses Laravel's orderedUuid() which generates time-ordered UUIDs
     * for better database B-tree index performance.
     *
     * @return string UUID v4 string representation
     */
    public function generate(): string
    {
        return Str::orderedUuid()->toString();
    }
}
