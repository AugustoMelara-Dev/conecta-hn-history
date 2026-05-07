<?php

declare(strict_types=1);

namespace App\Domain\Interactions\Repositories;

use App\Domain\Analytics\Entities\InteractionEntity;

interface InteractionRepositoryInterface
{
    public function create(InteractionEntity $entity): void;

    public function exists(string $tenantId, array $criteria): bool;
}
