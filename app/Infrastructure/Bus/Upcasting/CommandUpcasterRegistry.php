<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Upcasting;

use App\Domain\Shared\Contracts\UpcasterInterface;

/**
 * CommandUpcasterRegistry - Sequential Schema Migration Pipeline
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Intercept raw JSON payloads before Command hydration and     ║
 * ║  apply registered Upcasters to migrate V(n) → V(current) schemas.     ║
 * ║  CHAIN: Iterates all registered upcasters, applying each that matches ║
 * ║  the command type and current payload version, in ascending order.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * The registry does NOT mutate the original Outbox record — it transforms
 * the payload in-flight during consumption.
 */
final readonly class CommandUpcasterRegistry
{
    /** @var UpcasterInterface[] */
    private array $sortedUpcasters;

    /**
     * @param  iterable<UpcasterInterface>  $upcasters
     */
    public function __construct(iterable $upcasters = [])
    {
        // Sort by fromVersion ascending to ensure sequential V1→V2→V3 processing
        $sorted = is_array($upcasters) ? $upcasters : iterator_to_array($upcasters);
        usort($sorted, fn (UpcasterInterface $a, UpcasterInterface $b) => $a->fromVersion() <=> $b->fromVersion());
        $this->sortedUpcasters = $sorted;
    }

    /**
     * Process a payload through all applicable upcasters sequentially.
     *
     * Starts at $currentVersion and applies each matching upcaster,
     * advancing the version counter after each transformation.
     *
     * @param  string  $commandClass  FQCN of the Command (e.g., RegisterUserCommand)
     * @param  array<string, mixed>  $payload  Raw JSON-decoded payload from Outbox/Queue
     * @param  int  $currentVersion  The schema version of the incoming payload
     * @return array<string, mixed>  The transformed payload at the latest version
     */
    public function process(string $commandClass, array $payload, int $currentVersion = 1): array
    {
        $version = $currentVersion;

        foreach ($this->sortedUpcasters as $upcaster) {
            if ($upcaster->supports($commandClass, $version)) {
                $payload = $upcaster->upcast($payload);
                $version = $upcaster->toVersion();
            }
        }

        return $payload;
    }

    /**
     * Get the final version after processing all applicable upcasters.
     *
     * Useful for asserting that the payload was fully migrated.
     */
    public function resolveLatestVersion(string $commandClass, int $startVersion = 1): int
    {
        $version = $startVersion;

        foreach ($this->sortedUpcasters as $upcaster) {
            if ($upcaster->supports($commandClass, $version)) {
                $version = $upcaster->toVersion();
            }
        }

        return $version;
    }
}
