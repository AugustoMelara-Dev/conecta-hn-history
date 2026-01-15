<?php

declare(strict_types=1);

namespace App\Domain\Common;

use DateTimeImmutable;

/**
 * DomainEvent Interface
 * 
 * Defines the contract for all Domain Events in the system.
 * Enforces serializability to agnostic formats (JSON/Array).
 */
interface DomainEvent
{
    /**
     * Convert the event state to a primitive array.
     * 
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * Reconstruct the event from a primitive array.
     * 
     * @param array<string, mixed> $payload
     * @return self
     */
    public static function fromPayload(array $payload): self;

    /**
     * Get the timestamp when the event occurred.
     * 
     * @return DateTimeImmutable
     */
    public function occurredOn(): DateTimeImmutable;
}
