<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry\Commands;

final readonly class RollbackAgentActivationCommand
{
    public function __construct(
        public string $actorId,
        public ?string $tenantId,
        public string $activationId,
        public int $expectedLockVersion,
        public string $reason,
    ) {}
}
