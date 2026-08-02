<?php

declare(strict_types=1);

namespace App\Infrastructure\Discovery;

use App\Domain\Discovery\SemanticRetrievalAdapter;

final class NullSemanticRetrievalAdapter implements SemanticRetrievalAdapter
{
    /** @param list<array<string, mixed>> $candidates @return array<string, int> */
    public function score(string $queryText, array $candidates): array
    {
        return [];
    }
}
