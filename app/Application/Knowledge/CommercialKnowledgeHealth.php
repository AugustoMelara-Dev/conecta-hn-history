<?php

declare(strict_types=1);

namespace App\Application\Knowledge;

final readonly class CommercialKnowledgeHealth
{
    /** @param list<array{code:string,label:string,action_url:string,severity:string}> $issues */
    public function __construct(
        public int $score,
        public bool $publiclyResolvable,
        public array $issues,
        public \DateTimeImmutable $evaluatedAt,
    ) {}
}
