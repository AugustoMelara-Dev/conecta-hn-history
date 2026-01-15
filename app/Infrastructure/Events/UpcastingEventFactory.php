<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Common\EventUpcaster;
use RuntimeException;

final class UpcastingEventFactory
{
    /** @var EventUpcaster[] */
    private array $upcasters = [];

    /**
     * @param iterable<EventUpcaster> $upcasters
     */
    public function __construct(iterable $upcasters)
    {
        foreach ($upcasters as $upcaster) {
            $this->upcasters[] = $upcaster;
        }
    }

    /**
     * @template T
     * @param class-string<T> $eventClass
     * @param array<string, mixed> $payload
     */
    public function fromPayload(string $eventClass, array $payload, int $payloadVersion): object
    {
        // Assume target version is defined in the event class constant, logic dependent on implementation.
        // For compliance with "The Always-Current Law", we assume the class represents the LATEST version.
        // We need to know the target version. Assuming a convention or constant.
        // If not defined, we assume strict match required.
        
        $targetVersion = defined("{$eventClass}::VERSION") ? constant("{$eventClass}::VERSION") : 1;

        while ($payloadVersion < $targetVersion) {
            $upcasterFound = false;

            foreach ($this->upcasters as $upcaster) {
                if ($upcaster->supports($eventClass, $payloadVersion)) {
                    $payload = $upcaster->upcast($payload);
                    $payloadVersion++; // Move to next version
                    $upcasterFound = true;
                    break;
                }
            }

            if (!$upcasterFound) {
                throw new RuntimeException(
                    "Data Integrity Violation: No upcaster found for event {$eventClass} from v{$payloadVersion} to v" . ($payloadVersion + 1)
                );
            }
        }

        // "The Always-Current Law": Domain only sees the latest structure.
        // Assuming the event class has a static fromPayload or constructor that takes the array.
        // Direct instantiation or static factory method call.
        if (method_exists($eventClass, 'fromPayload')) {
             return $eventClass::fromPayload($payload);
        }
        
        // Fallback to constructor unpacking (simple events)
        return new $eventClass(...$payload);
    }
}
