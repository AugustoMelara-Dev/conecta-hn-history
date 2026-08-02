<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\BusinessGraph\BusinessGraphEdge;
use App\Application\BusinessGraph\BusinessGraphFact;
use App\Application\BusinessGraph\BusinessGraphSnapshot;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class WorkforceAgentContext
{
    public function __construct(
        public string $tenantId,
        public string $agentVersionId,
        public AgentRole $role,
        public WorkforceRoleConfiguration $configuration,
        public ?AiRuntimeSelection $selection = null,
    ) {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($agentVersionId)) {
            throw new InvalidArgumentException('Workforce context IDs must be UUIDs.');
        }

        if ($selection !== null && ! hash_equals($selection->agentVersionId, $agentVersionId)) {
            throw new InvalidArgumentException('Workforce context selection is not pinned to the requested agent version.');
        }

        if ($configuration->role !== $role) {
            throw new InvalidArgumentException('Workforce context role and configuration do not match.');
        }
    }

    /** @return array<string, mixed> */
    public function systemContext(BusinessGraphSnapshot $snapshot): array
    {
        if (! hash_equals($this->tenantId, $snapshot->tenantId)) {
            throw new InvalidArgumentException('Workforce context cannot consume another tenant business graph.');
        }

        $businessContext = [
            'facts' => array_map(fn (BusinessGraphFact $fact): array => [
                'subjectType' => $fact->subjectType,
                'subjectId' => $fact->subjectId,
                'factKey' => $fact->factKey,
                'value' => $fact->value,
                'sourceType' => $fact->sourceType,
                'sourceId' => $fact->sourceId,
            ], $snapshot->facts),
            'edges' => array_map(fn (BusinessGraphEdge $edge): array => [
                'fromType' => $edge->fromType,
                'fromId' => $edge->fromId,
                'relation' => $edge->relation,
                'toType' => $edge->toType,
                'toId' => $edge->toId,
                'sourceType' => $edge->sourceType,
                'sourceId' => $edge->sourceId,
            ], $snapshot->edges),
        ];
        $version = 'business-graph:v1:'.hash(
            'sha256',
            json_encode($businessContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return [
            'tenantId' => $this->tenantId,
            'agentVersionId' => $this->agentVersionId,
            'role' => $this->role->value,
            'objective' => $this->configuration->objective,
            'allowedJobs' => $this->configuration->allowedJobs,
            'riskProfile' => $this->configuration->riskProfile,
            'metricKeys' => $this->configuration->metricKeys,
            'businessContextVersion' => $version,
            'businessContext' => $businessContext,
        ];
    }
}
