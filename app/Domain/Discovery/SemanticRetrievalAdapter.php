<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

interface SemanticRetrievalAdapter
{
    /**
     * Score only the already-filtered public candidates.
     *
     * Implementations may use a deterministic semantic/vector provider, but
     * must return scores keyed by candidate projection id and must not add
     * candidates, bypass market filters, or replace canonical facts.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, int>
     */
    public function score(string $queryText, array $candidates): array;
}
