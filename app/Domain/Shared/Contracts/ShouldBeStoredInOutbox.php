<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * ShouldBeStoredInOutbox - Marker Interface for Transactional Events
 *
 * Events implementing this interface will be:
 * 1. Intercepted by TransactionalEventDispatcher
 * 2. Serialized and stored in domain_outbox_messages table
 * 3. Dispatched asynchronously by the Outbox Worker
 *
 * This guarantees at-least-once delivery even if the application
 * crashes after database commit but before event dispatch.
 */
interface ShouldBeStoredInOutbox
{
    /**
     * Convert event to serializable payload.
     *
     * RESTRICTIONS:
     * - Must return only primitives, arrays, or nested arrays
     * - Must NOT include Eloquent models or objects with resources
     * - Should include all data needed to reconstruct the event
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * Reconstruct event from serialized payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static;

    /**
     * Get the aggregate identifier for debugging/filtering.
     */
    public function getAggregateId(): ?string;

    /**
     * Get the aggregate type (entity class) for debugging/filtering.
     */
    public function getAggregateType(): ?string;
}
