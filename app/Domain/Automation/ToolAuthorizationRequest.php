<?php

declare(strict_types=1);

namespace App\Domain\Automation;

final readonly class ToolAuthorizationRequest
{
    /**
     * @param  list<string>  $actorRoles
     */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public array $actorRoles,
        public string $plan,
        public string $channel,
        public string $tool,
        public string $toolVersion,
        public string $risk,
        public string $payloadHash,
        public string $idempotencyKey,
        public ?string $confirmationId,
        public ?string $confirmationToken,
    ) {}
}
