<?php

declare(strict_types=1);

namespace App\Domain\Common\Contracts;

use DateTimeImmutable;

/**
 * DomainEventInterface - The Integrity Contract
 *
 * Defines the essential behavior of any historical fact in the system.
 */
interface DomainEventInterface
{
    /**
     * Convert the event state to a primitive array for storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * Reconstruct the event from a primitive array.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static;

    /**
     * Get the exact moment the event occurred in the Domain (not Infrastructure).
     */
    public function occurredOn(): DateTimeImmutable;

    /**
     * Get the ID of the Aggregate Root associated with this event.
     */
    public function aggregateId(): string;
}
