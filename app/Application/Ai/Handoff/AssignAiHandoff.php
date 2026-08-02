<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

final readonly class AssignAiHandoff
{
    public function __construct(
        public string $tenantId,
        public string $handoffId,
        public string $actorId,
        public string $assigneeId,
        public int $expectedVersion,
    ) {}
}
