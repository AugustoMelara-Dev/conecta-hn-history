<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface UpcasterInterface
{
    /**
     * Determine if this upcaster supports the given event type and version.
     */
    public function supports(string $eventType, string $sourceVersion): bool;

    /**
     * Get the version this upcaster transforms TO.
     */
    public function getTargetVersion(): string;

    /**
     * Transform the payload from the source version to the next version.
     */
    public function upcast(array $payload): array;
}
