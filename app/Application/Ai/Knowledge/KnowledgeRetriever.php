<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge;

interface KnowledgeRetriever
{
    /** @return list<array<string, mixed>> */
    public function searchPublic(string $query, int $limit = 12): array;

    /** @return list<array<string, mixed>> */
    public function searchTenant(string $query, string $tenantId, int $limit = 12): array;
}
