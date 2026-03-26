<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\OperationalPlaybookTask;

interface OperationalPlaybookTaskRepositoryInterface
{
    public function findOpenTask(
        string $tenantId,
        string $triggerKey,
        ?string $relatedType,
        ?string $relatedId,
    ): ?OperationalPlaybookTask;

    public function save(OperationalPlaybookTask $task): void;
}
