<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Models\Service;

interface ServiceRepositoryInterface
{
    public function findById(string|int $id): ?Service;

    /**
     * @return array<int, array{
     *     id: string|int,
     *     name: string,
     *     description: string|null,
     *     formatted_price: string,
     *     duration_minutes: int,
     *     formatted_duration: string
     * }>
     */
    public function getActiveForTenant(string|int $tenantId): array;
}
