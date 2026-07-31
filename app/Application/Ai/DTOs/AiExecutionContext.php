<?php

declare(strict_types=1);

namespace App\Application\Ai\DTOs;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AiExecutionContext
{
    /** @param list<string> $allowedTools */
    public function __construct(
        public string $correlationId,
        public string $audience,
        public string $capability,
        public ?string $tenantId,
        public string $billingTenantId,
        public int|string|null $actorId,
        public bool $authorized,
        public bool $entitled,
        public string $rateLimitSubject,
        public array $allowedTools = [],
        public ?string $conversationId = null,
        public ?string $requestMessageId = null,
    ) {
        if (! Str::isUuid($correlationId)) {
            throw new InvalidArgumentException('AI correlation ID must be a UUID.');
        }

        if (! in_array($audience, ['public', 'private', 'internal'], true)) {
            throw new InvalidArgumentException('AI audience is invalid.');
        }

        if (! preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $capability)) {
            throw new InvalidArgumentException('AI capability is invalid.');
        }

        if ($tenantId !== null && ! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('AI tenant ID must be a UUID.');
        }

        if (! Str::isUuid($billingTenantId)) {
            throw new InvalidArgumentException('AI billing tenant ID must be a UUID.');
        }

        if (! preg_match('/^[a-f0-9]{64}$/', $rateLimitSubject)) {
            throw new InvalidArgumentException('AI rate-limit subject must be a server-side SHA-256 digest.');
        }

        if (($conversationId === null) !== ($requestMessageId === null)
            || ($conversationId !== null && ! Str::isUuid($conversationId))
            || ($requestMessageId !== null && ! Str::isUuid($requestMessageId))) {
            throw new InvalidArgumentException('AI conversation execution lineage is invalid.');
        }
    }
}
