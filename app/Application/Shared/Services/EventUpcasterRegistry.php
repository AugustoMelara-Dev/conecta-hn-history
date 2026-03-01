<?php

declare(strict_types=1);

namespace App\Application\Shared\Services;

use App\Application\Shared\Contracts\UpcasterInterface;
use RuntimeException;

/**
 * EventUpcasterRegistry - Sequential Event Schema Evolution
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PATTERN: Chain of Responsibility for versioned event replay.          ║
 * ║  IMMUTABLE: No global state mutation. Pure payload transformation.     ║
 * ║  TENANT SAFE: Operates only on raw array payloads.                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EventUpcasterRegistry
{
    /** @var UpcasterInterface[] */
    private array $upcasters = [];

    public function register(UpcasterInterface $upcaster): void
    {
        $this->upcasters[] = $upcaster;
    }

    /**
     * Apply sequential upcasting chain: V(n) → V(n+1) → ... → V(target).
     *
     * @param  string  $eventType       Canonical event type (e.g., 'App\Domain\Identity\Events\UserRegistered')
     * @param  int     $currentVersion  The schema version stored in the payload
     * @param  array<string, mixed>  $payload  The raw payload from storage (JSON decoded)
     * @return array{version: int, payload: array<string, mixed>}
     *
     * @throws RuntimeException If a gap in the upcasting chain is detected
     */
    public function upcast(string $eventType, int $currentVersion, array $payload): array
    {
        $upcastedPayload = $payload;
        $version = $currentVersion;
        $maxIterations = 100; // Safety guard against infinite loops
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $upcaster = $this->findUpcasterFor($eventType, $version);

            if ($upcaster === null) {
                break; // No more upcasters in the chain — done
            }

            if ($upcaster->fromVersion() !== $version) {
                throw new RuntimeException(
                    sprintf(
                        'Upcasting gap detected for %s: expected version %d, but next upcaster is from version %d',
                        $eventType,
                        $version,
                        $upcaster->fromVersion()
                    )
                );
            }

            $upcastedPayload = $upcaster->upcast($upcastedPayload);
            $version = $upcaster->toVersion();
            $iterations++;
        }

        return [
            'version' => $version,
            'payload' => $upcastedPayload,
        ];
    }

    /**
     * Find the first upcaster that supports the given event type and version.
     */
    private function findUpcasterFor(string $eventType, int $version): ?UpcasterInterface
    {
        foreach ($this->upcasters as $upcaster) {
            if ($upcaster->supports($eventType, $version)) {
                return $upcaster;
            }
        }

        return null;
    }
}
