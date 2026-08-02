<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use Carbon\CarbonImmutable;

final readonly class WorkflowStartRequest
{
    /**
     * @param  list<string>  $actorRoles
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{confirmation_id?: string, confirmation_token?: string}>  $confirmations
     */
    public function __construct(
        public string $tenantId,
        public string $workflowKey,
        public string $workflowVersion,
        public string $actorId,
        public array $actorRoles,
        public string $plan,
        public string $channel,
        public string $idempotencyKey,
        public string $trigger,
        public array $payload,
        public array $confirmations = [],
        public ?CarbonImmutable $requestedAt = null,
    ) {}

    public function occurredAt(): CarbonImmutable
    {
        return $this->requestedAt ?? CarbonImmutable::now();
    }
}
