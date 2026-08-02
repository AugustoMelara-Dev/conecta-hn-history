<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

final readonly class ResolveAiHandoff
{
    public function __construct(
        public string $tenantId,
        public string $handoffId,
        public string $actorId,
        public int $expectedVersion,
        public string $resolutionNote,
        public bool $returnToAgent,
    ) {}
}
