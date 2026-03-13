<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface ServiceProviderRepositoryInterface
{
    public function findById(string|int $id): ?object;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveForTenant(string|int $tenantId): array;

    public function isAvailableAt(string|int $providerId, \DateTimeInterface $start, \DateTimeInterface $end, int $bufferBefore = 0, int $bufferAfter = 0): bool;

    public function getOpeningHours(string|int $providerId): ?\Spatie\OpeningHours\OpeningHours;
}
