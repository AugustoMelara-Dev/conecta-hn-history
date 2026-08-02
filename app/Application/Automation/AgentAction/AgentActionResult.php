<?php

declare(strict_types=1);

namespace App\Application\Automation\AgentAction;

final readonly class AgentActionResult
{
    public function __construct(
        public bool $executed,
        public string $status,
        public string $reasonCode,
        public ?string $workflowRunId,
        public bool $replayed,
        public AgentActionAudit $audit,
    ) {}
}
