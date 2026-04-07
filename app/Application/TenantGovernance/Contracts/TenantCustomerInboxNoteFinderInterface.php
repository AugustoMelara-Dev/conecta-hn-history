<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Models\CustomerNote;

interface TenantCustomerInboxNoteFinderInterface
{
    public function findNote(string $tenantId, int $noteId): ?CustomerNote;
}
