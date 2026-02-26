<?php

declare(strict_types=1);

namespace App\Domain\Interactions\Repositories;

use App\Models\Interaction;

interface InteractionRepositoryInterface
{
    public function create(array $data, string $tenantId): Interaction;

    public function exists(string $tenantId, array $criteria): bool;
}
