<?php

declare(strict_types=1);

namespace App\Application\Ai\Observability;

final readonly class AiFoundationHealthSnapshot
{
    public function __construct(
        public bool $ready,
        public string $registryStatus,
        public int $eligibleRouteCount,
        public int $accountingPending,
        public int $retentionBacklog,
        public ?string $lastSuccessfulEvaluationAt,
    ) {}
}
