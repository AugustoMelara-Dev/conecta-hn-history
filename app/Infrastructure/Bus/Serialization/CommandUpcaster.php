<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Serialization;

use App\Application\Shared\Contracts\UpcasterInterface;

/**
 * CommandUpcaster - Schema Evolution Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Applies registered UpcasterInterface transformations to raw   ║
 * ║  Command/Event payloads before deserialization.                          ║
 * ║  PATTERN: Chain of Responsibility with version-ordered execution.       ║
 * ║  IMMUTABLE: Does not mutate upcasters or global state.                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Usage: Queue/Outbox workers call process() on raw JSON-decoded payloads
 * to transparently upgrade old schema versions to the current version
 * before the Command/Event is instantiated.
 *
 * @see App\Application\Shared\Contracts\UpcasterInterface
 */
final class CommandUpcaster
{
    /**
     * @var UpcasterInterface[]
     */
    private array $upcasters;

    /**
     * @param  UpcasterInterface[]  $upcasters  Registered upcasters (order does not matter — sorted internally).
     */
    public function __construct(array $upcasters = [])
    {
        $this->upcasters = $upcasters;
    }

    /**
     * Process a raw payload through the upcaster chain.
     *
     * Applies all matching upcasters sequentially, ordered by source version,
     * until no more transformations are applicable.
     *
     * @param  array<string, mixed>  $payload  Raw payload with 'event_type' and 'schema_version' keys.
     * @return array<string, mixed>  Fully upcasted payload.
     *
     * @throws \InvalidArgumentException If payload is missing required keys.
     */
    public function process(array $payload): array
    {
        $eventType = $payload['event_type'] ?? null;
        if ($eventType === null) {
            throw new \InvalidArgumentException(
                'Payload must contain "event_type" key.'
            );
        }

        // Default to version 1 if not present (Schema Evolution Contract)
        $schemaVersion = $payload['schema_version'] ?? 1;
        $currentVersion = (int) $schemaVersion;

        // Sort upcasters by fromVersion to ensure sequential V1→V2→V3 application
        $sorted = $this->upcasters;
        usort($sorted, fn (UpcasterInterface $a, UpcasterInterface $b) => $a->fromVersion() <=> $b->fromVersion());

        foreach ($sorted as $upcaster) {
            // Only apply upcasters that support the specific event type AND the current version
            if ($upcaster->supports($eventType, $currentVersion)) {
                $payload = $upcaster->upcast($payload);
                $currentVersion = $upcaster->toVersion();
                // Update schema version in payload to reflect the change
                $payload['schema_version'] = $currentVersion;
            }
        }

        return $payload;
    }

    /**
     * Register an additional upcaster at runtime.
     */
    public function register(UpcasterInterface $upcaster): void
    {
        $this->upcasters[] = $upcaster;
    }

    /**
     * Get the count of registered upcasters.
     */
    public function count(): int
    {
        return count($this->upcasters);
    }
}
