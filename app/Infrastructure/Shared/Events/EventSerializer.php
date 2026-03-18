<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Events;

use App\Domain\Shared\Contracts\UpcasterInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * EventSerializer - Upcasting-Aware Event Hydrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CORE FUNCTION: Serialize domain events TO storage, hydrate FROM storage ║
 * ║  UPCASTING: Automatically transforms old schema versions to current     ║
 * ║  EVOLVED DESIGN: Supports version chains (V1→V2→V3→Current)             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class EventSerializer
{
    /**
     * @param  array<UpcasterInterface>  $upcasters  Registered upcasters for all event types
     * @param  array<string, class-string>  $eventTypeMap  Maps event type strings to FQCN
     * @param  array<string, int>  $currentVersions  Maps event type to current schema version
     */
    public function __construct(
        private readonly \App\Application\Shared\Contracts\IdGeneratorInterface $idGenerator,
        private array $upcasters = [],
        private array $eventTypeMap = [],
        private array $currentVersions = [],
    ) {}

    /**
     * Serialize a domain event to storage format.
     *
     * @param  object  $event  The domain event instance
     * @return array<string, mixed> JSON-encodable array for storage
     */
    public function serialize(object $event): array
    {
        $eventClass = $event::class;

        if (! method_exists($event, 'toPayload')) {
            throw new InvalidArgumentException(
                "Event class {$eventClass} must implement toPayload() method"
            );
        }

        if (! method_exists($event, 'eventName')) {
            throw new InvalidArgumentException(
                "Event class {$eventClass} must implement eventName() method"
            );
        }

        if (! method_exists($event, 'schemaVersion')) {
            throw new InvalidArgumentException(
                "Event class {$eventClass} must implement schemaVersion() method"
            );
        }

        return [
            'event_id' => $this->generateEventId(),
            'type' => $event->eventName(),
            'schema_version' => $event->schemaVersion(),
            'payload' => $event->toPayload(),
        ];
    }

    /**
     * Hydrate a domain event from storage, applying upcasting if needed.
     *
     * @param  string  $eventType  The canonical event type
     * @param  int  $storedVersion  The schema version from storage
     * @param  array<string, mixed>  $rawPayload  The raw payload from database
     * @return object The fully hydrated domain event
     */
    public function hydrate(string $eventType, int $storedVersion, array $rawPayload): object
    {
        $eventClass = $this->resolveEventClass($eventType);
        $currentVersion = $this->getCurrentVersion($eventType);

        // Build full envelope for upcasting
        $envelope = [
            'event_id' => $rawPayload['event_id'] ?? $this->generateEventId(),
            'type' => $eventType,
            'schema_version' => $storedVersion,
            'payload' => $rawPayload['payload'] ?? $rawPayload,
        ];

        // Apply upcasting chain if stored version is behind current
        if ($storedVersion < $currentVersion) {
            $envelope = $this->applyUpcastingChain($eventType, $envelope, $storedVersion, $currentVersion);
        }

        // Instantiate via fromPayload factory
        if (! method_exists($eventClass, 'fromPayload')) {
            throw new RuntimeException(
                "Event class {$eventClass} must implement static fromPayload() method"
            );
        }

        return $eventClass::fromPayload($envelope['payload']);
    }

    /**
     * Apply sequential upcasters to evolve payload from old to current version.
     *
     * @param  string  $eventType  The event type being upcasted
     * @param  array<string, mixed>  $envelope  The full event envelope
     * @param  int  $fromVersion  Starting schema version
     * @param  int  $toVersion  Target schema version
     * @return array<string, mixed> The evolved envelope
     */
    private function applyUpcastingChain(
        string $eventType,
        array $envelope,
        int $fromVersion,
        int $toVersion
    ): array {
        $currentVersion = $fromVersion;

        while ($currentVersion < $toVersion) {
            $upcaster = $this->findUpcaster($eventType, $currentVersion);

            if ($upcaster === null) {
                throw new RuntimeException(
                    "No upcaster found for event '{$eventType}' version {$currentVersion}. ".
                    "Cannot evolve from v{$fromVersion} to v{$toVersion}."
                );
            }

            $envelope = $upcaster->upcast($envelope);
            $currentVersion = $upcaster->toVersion();
        }

        return $envelope;
    }

    /**
     * Find an upcaster that handles the given event type and version.
     */
    private function findUpcaster(string $eventType, int $version): ?UpcasterInterface
    {
        foreach ($this->upcasters as $upcaster) {
            if ($upcaster->supports($eventType, $version)) {
                return $upcaster;
            }
        }

        return null;
    }

    /**
     * Resolve event type string to FQCN.
     */
    private function resolveEventClass(string $eventType): string
    {
        if (! isset($this->eventTypeMap[$eventType])) {
            throw new InvalidArgumentException(
                "Unknown event type: '{$eventType}'. Register it in the eventTypeMap."
            );
        }

        return $this->eventTypeMap[$eventType];
    }

    /**
     * Get the current schema version for an event type.
     */
    private function getCurrentVersion(string $eventType): int
    {
        return $this->currentVersions[$eventType] ?? 1;
    }

    /**
     * Generate a unique event ID.
     */
    private function generateEventId(): string
    {
        return $this->idGenerator->generate();
    }
}
