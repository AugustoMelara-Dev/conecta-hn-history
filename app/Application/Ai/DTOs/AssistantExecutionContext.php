<?php

declare(strict_types=1);

namespace App\Application\Ai\DTOs;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AssistantExecutionContext
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $correlationId,
        public string $marketCode = 'HN',
        public string $residencyClass = 'HN',
        public bool $authorized = true,
        public bool $entitled = true,
    ) {
        if (! Str::isUuid($tenantId) || trim($actorId) === '' || ! Str::isUuid($correlationId)) {
            throw new InvalidArgumentException('AI assistant tenant, actor and correlation context is required.');
        }

        if (! preg_match('/^[A-Z]{2}$/', $marketCode) || ! preg_match('/^[A-Z]{2}$/', $residencyClass)) {
            throw new InvalidArgumentException('AI assistant market context is invalid.');
        }
    }
}
