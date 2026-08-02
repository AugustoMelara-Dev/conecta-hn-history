<?php

declare(strict_types=1);

namespace App\Application\Automation\AgentAction;

use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class AgentOutcomeRecorder
{
    private const TYPES = [
        'lead_qualified',
        'quote_created',
        'reservation_created',
        'order_created',
        'support_resolved',
        'task_completed',
        'handoff_requested',
    ];

    public function __construct(
        private DatabaseWorkflowRepository $repository,
        private OutboxRepositoryInterface $outbox,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function record(AgentActionResult $result, string $outcomeType, array $attributes = []): string
    {
        if (! $result->executed || $result->status !== 'completed') {
            throw new LogicException('Only completed agent actions can record outcomes.');
        }
        if (! in_array($outcomeType, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported agent outcome type.');
        }

        $audit = $this->repository->findAgentActionAuditById($result->audit->id, $result->audit->tenantId);
        if ($audit === null) {
            throw new LogicException('Agent action audit is missing or belongs to another tenant.');
        }

        $aggregateType = self::nullableString($attributes['aggregate_type'] ?? null, 64);
        $aggregateId = self::nullableString($attributes['aggregate_id'] ?? null, 128);
        $rawAmountMinor = $attributes['amount_minor'] ?? null;
        if ($rawAmountMinor !== null
            && (! is_int($rawAmountMinor)
                && (! is_string($rawAmountMinor) || preg_match('/^\d+$/', $rawAmountMinor) !== 1))) {
            throw new InvalidArgumentException('Agent outcome amount must be an integer minor-unit value.');
        }
        $amountMinor = $rawAmountMinor === null ? null : (int) $rawAmountMinor;
        $currency = isset($attributes['currency']) ? strtoupper((string) $attributes['currency']) : null;
        if (($amountMinor === null) !== ($currency === null)) {
            throw new InvalidArgumentException('Agent outcome money must include both minor units and currency.');
        }
        if ($amountMinor !== null && $currency !== null) {
            Money::of($amountMinor, $currency);
        }
        $metadata = AgentActionAudit::sanitize($attributes);
        $outcomeId = (string) Str::uuid();

        DB::transaction(function () use ($result, $outcomeType, $aggregateType, $aggregateId, $amountMinor, $currency, $metadata, $outcomeId): void {
            $trustedOutcomeId = $this->trustedOutcomeId($result->audit->tenantId, $aggregateType, $aggregateId);
            $this->repository->createAgentOutcome([
                'id' => $outcomeId,
                'tenant_id' => $result->audit->tenantId,
                'audit_id' => $result->audit->id,
                'agent_version_id' => $result->audit->agentVersionId,
                'conversation_id' => $result->audit->conversationId,
                'action_key' => $result->audit->actionKey,
                'outcome_type' => $outcomeType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'verified_commercial_outcome_id' => $trustedOutcomeId,
                'attribution_metadata' => $metadata,
                'payload_hash' => hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->repository->updateAgentActionAudit($result->audit->id, ['outcome_id' => $outcomeId, 'updated_at' => now()]);
            $this->outbox->append(new BusinessOperationChanged(
                aggregateId: 'agent_outcome:'.$outcomeId,
                aggregateType: 'agent_outcome',
                tenantId: $result->audit->tenantId,
                actorId: $result->audit->agentVersionId,
                eventName: 'agent_outcome_recorded',
                attributes: AgentActionAudit::sanitize([
                    'outcome_type' => $outcomeType,
                    'action_key' => $result->audit->actionKey,
                    'aggregate_type' => $aggregateType,
                    'aggregate_id' => $aggregateId,
                    'trusted_outcome_linked' => $trustedOutcomeId !== null,
                ]),
                occurredAt: now()->toImmutable(),
            ));
        }, 3);

        return $outcomeId;
    }

    private function trustedOutcomeId(string $tenantId, ?string $aggregateType, ?string $aggregateId): ?int
    {
        if ($aggregateType === null || $aggregateId === null) {
            return null;
        }

        $id = DB::table('verified_commercial_outcomes')
            ->where('tenant_id', $tenantId)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private static function nullableString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            throw new InvalidArgumentException('Agent outcome canonical reference is invalid.');
        }

        return trim($value);
    }
}
