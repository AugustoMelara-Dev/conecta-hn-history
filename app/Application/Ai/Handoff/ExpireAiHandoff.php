<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

final readonly class ExpireAiHandoff
{
    public function __construct(
        public string $tenantId,
        public string $handoffId,
        public int $expectedVersion,
    ) {}
}
