<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

final readonly class AiEvaluationDecision
{
    /** @param list<string> $blockingFailures */
    public function __construct(
        public string $runId,
        public string $status,
        public EvaluationMetrics $metrics,
        public array $blockingFailures,
        public string $resultChecksum,
        public bool $reused = false,
    ) {}
}
