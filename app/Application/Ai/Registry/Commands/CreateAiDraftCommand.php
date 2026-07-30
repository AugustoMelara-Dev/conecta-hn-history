<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry\Commands;

final readonly class CreateAiDraftCommand
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $actorId,
        public ?string $tenantId,
        public string $versionType,
        public string $definitionId,
        public array $payload,
        public string $reason,
    ) {}
}
