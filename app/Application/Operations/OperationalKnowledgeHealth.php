<?php

declare(strict_types=1);

namespace App\Application\Operations;

interface OperationalKnowledgeHealth
{
    /** @return list<OperationalKnowledgeIssue> */
    public function forTenant(string $tenantId): array;
}
