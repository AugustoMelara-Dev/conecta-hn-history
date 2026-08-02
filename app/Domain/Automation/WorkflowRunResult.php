<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use Carbon\CarbonImmutable;

final readonly class WorkflowRunResult
{
    /**
     * @param  array<string, mixed>  $output
     */
    public function __construct(
        public string $runId,
        public string $status,
        public ?string $currentStepId,
        public bool $replayed,
        public ?CarbonImmutable $waitingUntil,
        public array $output = [],
    ) {}
}
