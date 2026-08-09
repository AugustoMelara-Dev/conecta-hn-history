<?php

declare(strict_types=1);

namespace App\Application\Automation\AgentAction;

use Carbon\CarbonImmutable;

final readonly class AgentActionAudit
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $agentVersionId,
        public string $conversationId,
        public string $actionKey,
        public string $policyCode,
        public ?string $confirmationId,
        public ?string $workflowRunId,
        public ?string $outcomeId,
        public CarbonImmutable $occurredAt,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant' => $this->tenantId,
            'agent' => $this->agentVersionId,
            'conversation_id' => $this->conversationId,
            'action' => $this->actionKey,
            'policy_decision' => $this->policyCode,
            'confirmation_id' => $this->confirmationId,
            'workflow_run_id' => $this->workflowRunId,
            'outcome_id' => $this->outcomeId,
            'timestamp' => $this->occurredAt->toIso8601String(),
            'metadata' => self::sanitize($this->metadata),
        ];
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function sanitize(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);
            if (preg_match('/(?:password|token|secret|authorization|api[_-]?key|credential|cookie|email|phone|address)/', $normalized) === 1) {
                continue;
            }
            $safe[(string) $key] = is_array($value) ? self::sanitize($value) : $value;
        }

        return $safe;
    }
}
