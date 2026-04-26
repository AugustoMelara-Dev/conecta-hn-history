<?php

declare(strict_types=1);

namespace App\Application\BusinessAi\Contracts;

use App\Models\Tenant;

interface BusinessRagServiceInterface
{
    /**
     * @return array{answer:string,sources:list<array<string,mixed>>,meta:array<string,mixed>}
     */
    public function answer(Tenant $tenant, string $query, ?string $userId = null, int $limit = 6): array;
}
