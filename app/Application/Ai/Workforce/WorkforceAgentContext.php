<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

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
    ) {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($agentVersionId)) {
            throw new InvalidArgumentException('Workforce context IDs must be UUIDs.');
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

        foreach ($snapshot->facts as $fact) {
            if (! $fact instanceof BusinessGraphFact || ! hash_equals($this->tenantId, $fact->tenantId)) {
                throw new InvalidArgumentException('Workforce context received a mixed-tenant business fact.');
            }
        }

        foreach ($snapshot->edges as $edge) {
            if (! $edge instanceof BusinessGraphEdge || ! hash_equals($this->tenantId, $edge->tenantId)) {
                throw new InvalidArgumentException('Workforce context received a mixed-tenant business edge.');
            }
        }

        $facts = array_map(fn (BusinessGraphFact $fact): array => [
            'subjectType' => $fact->subjectType,
            'subjectId' => $fact->subjectId,
            'factKey' => $this->isSensitiveKey($fact->factKey) ? '[redacted]' : $fact->factKey,
            'value' => $this->isSensitiveKey($fact->factKey)
                || $this->isSensitiveKey($fact->sourceType)
                || $this->isSensitiveKey($fact->sourceId)
                ? '[redacted]'
                : $this->sanitize($fact->value),
            'sourceType' => $this->isSensitiveKey($fact->sourceType) ? '[redacted]' : $fact->sourceType,
            'sourceId' => $this->isSensitiveKey($fact->sourceId) ? '[redacted]' : $fact->sourceId,
        ], $snapshot->facts);
        $edges = array_map(fn (BusinessGraphEdge $edge): array => [
            'fromType' => $edge->fromType,
            'fromId' => $edge->fromId,
            'relation' => $edge->relation,
            'toType' => $edge->toType,
            'toId' => $edge->toId,
            'sourceType' => $this->isSensitiveKey($edge->sourceType) ? '[redacted]' : $edge->sourceType,
            'sourceId' => $this->isSensitiveKey($edge->sourceId) ? '[redacted]' : $edge->sourceId,
        ], $snapshot->edges);

        $businessContext = ['facts' => $facts, 'edges' => $edges];
        $canonicalContext = [
            'facts' => $this->canonicalList($facts),
            'edges' => $this->canonicalList($edges),
        ];
        $version = 'business-graph:v1:'.hash(
            'sha256',
            json_encode($canonicalContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
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

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:secret|credential|token|password|api[_-]?key|private[_-]?key|prompt|cipher|authorization)/i', $key) === 1;
    }

    private function sanitize(mixed $value, string $key = ''): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if (! is_array($value)) {
            return is_scalar($value) || $value === null ? $value : (string) $value;
        }

        $result = [];
        foreach ($value as $childKey => $childValue) {
            $normalizedKey = is_string($childKey) ? $childKey : (string) $childKey;
            $result[$childKey] = $this->sanitize($childValue, $normalizedKey);
        }

        return $this->canonicalArray($result);
    }

    /** @param list<array<string, mixed>> $items */
    private function canonicalList(array $items): array
    {
        $items = array_map(fn (array $item): array => $this->canonicalArray($item), $items);
        usort($items, static fn (array $left, array $right): int => strcmp(
            json_encode($left, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            json_encode($right, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ));

        return $items;
    }

    /** @param array<array-key, mixed> $value */
    private function canonicalArray(array $value): array
    {
        if (array_is_list($value)) {
            $items = array_map(fn (mixed $item): mixed => is_array($item) ? $this->canonicalArray($item) : $item, $value);

            return $items;
        }

        ksort($value);
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $value[$key] = $this->canonicalArray($child);
            }
        }

        return $value;
    }
}
