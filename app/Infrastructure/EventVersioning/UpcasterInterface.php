<?php

declare(strict_types=1);

namespace App\Infrastructure\EventVersioning;

/**
 * UpcasterInterface - Schema Evolution Contract
 *
 * Responsible for transforming an older version of an event payload
 * into a newer version.
 */
interface UpcasterInterface
{
    /**
     * Check if this upcaster supports the given event type and version.
     *
     * @param  string  $eventType  Class name or event alias
     * @param  int  $sourceVersion  The version of the payload found in storage
     */
    public function supports(string $eventType, int $sourceVersion): bool;

    /**
     * Transform the payload to the next version.
     *
     * @param  array  $payload  The raw JSON payload from storage
     * @return array The upgraded payload
     */
    public function upcast(array $payload): array;
}
