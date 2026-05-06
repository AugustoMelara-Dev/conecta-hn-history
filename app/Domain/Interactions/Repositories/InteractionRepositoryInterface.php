<?php

declare(strict_types=1);

namespace App\Domain\Interactions\Repositories;

use App\Models\Interaction;

interface InteractionRepositoryInterface
{
    public function create(array $data, int $tenantId): Interaction;

    public function exists(int $tenantId, array $criteria): bool;
}
