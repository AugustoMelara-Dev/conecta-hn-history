<?php

declare(strict_types=1);

namespace App\Application\BusinessGraph\Contracts;

use App\Application\BusinessGraph\BusinessGraphEdge;
use App\Application\BusinessGraph\BusinessGraphFact;
use App\Application\BusinessGraph\BusinessGraphSnapshot;

interface BusinessGraphRepositoryInterface
{
    public function upsertFact(BusinessGraphFact $fact): void;

    public function upsertEdge(BusinessGraphEdge $edge): void;

    public function snapshot(string $tenantId): BusinessGraphSnapshot;
}
