<?php

declare(strict_types=1);

namespace App\Application\Operations;

interface OperationalCockpit
{
    public function snapshot(string $tenantId, string $actorId): OperationalCockpitSnapshot;
}
