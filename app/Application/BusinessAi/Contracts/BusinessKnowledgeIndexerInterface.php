<?php

declare(strict_types=1);

namespace App\Application\BusinessAi\Contracts;

use App\Models\Tenant;

interface BusinessKnowledgeIndexerInterface
{
    /**
     * @return array{indexed:int, skipped:int, sources:array<string,int>}
     */
    public function indexTenant(Tenant $tenant): array;
}
