<?php

declare(strict_types=1);

namespace App\Domain\Common;

interface EventUpcaster
{
    public function supports(string $eventType, int $sourceVersion): bool;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upcast(array $payload): array;
}
