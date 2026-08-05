<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

final readonly class AiEvaluationCaseResult
{
    /** @param list<string> $blockingCodes @param array<string, bool|float|int|string> $safeMetrics */
    public function __construct(
        public string $caseId,
        public string $caseKey,
        public string $status,
        public string $inputChecksum,
        public string $resultChecksum,
        public int $latencyMs,
        public float $costUsd,
        public array $safeMetrics,
        public array $blockingCodes,
        public string $output,
    ) {}
}
