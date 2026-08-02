<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use Carbon\CarbonImmutable;

final readonly class WorkflowResumeRequest
{
    /**
     * @param  list<string>  $actorRoles
     * @param  array<string, array{confirmation_id?: string, confirmation_token?: string}>  $confirmations
     */
    public function __construct(
        public string $actorId,
        public array $actorRoles,
        public string $plan,
        public string $channel,
        public array $confirmations = [],
        public ?CarbonImmutable $requestedAt = null,
    ) {}

    public function occurredAt(): CarbonImmutable
    {
        return $this->requestedAt ?? CarbonImmutable::now();
    }
}
