<?php

declare(strict_types=1);

namespace App\Application\BusinessGraph;

final readonly class BusinessGraphSnapshot
{
    /**
     * @param  list<BusinessGraphFact>  $facts
     * @param  list<BusinessGraphEdge>  $edges
     */
    public function __construct(
        public string $tenantId,
        public array $facts,
        public array $edges,
    ) {
        if (trim($tenantId) === '') {
            throw new \InvalidArgumentException('BusinessGraphSnapshot tenantId must not be empty.');
        }
    }
}
