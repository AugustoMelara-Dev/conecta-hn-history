<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Observability\AiFoundationHealth;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Application\BusinessGraph\Contracts\BusinessGraphRepositoryInterface;
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class TenantWorkforceController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantMarketResolver $markets,
        private readonly BusinessGraphRepositoryInterface $businessGraph,
        private readonly AiFoundationHealth $health,
        private readonly AiRegistryResolver $registry,
    ) {}

    public function activate(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validate([
            'role_key' => ['required', 'string', Rule::in(array_map(static fn (AgentRole $role): string => $role->value, AgentRoleCatalog::roles()))],
            'channel_keys' => ['nullable', 'array'],
            'channel_keys.*' => ['string', 'max:64'],
            'knowledge_source_ids' => ['nullable', 'array'],
            'knowledge_source_ids.*' => ['string', 'max:255'],
            'simulation_case_ids' => ['nullable', 'array'],
            'simulation_case_ids.*' => ['string', 'max:255'],
            'approval_mode' => ['required', 'string', Rule::in(['human_review', 'policy_gate'])],
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->whereKey($tenantId)->where('is_active', true)->firstOrFail();
        $market = $this->markets->resolveOrNull($tenant);
        $channels = $this->channels($tenantId);
        $requestedChannelKeys = array_values(array_unique($validated['channel_keys'] ?? []));
        $unknownChannels = array_values(array_diff($requestedChannelKeys, array_column($channels, 'key')));
        if ($unknownChannels !== []) {
            return response()->json(['code' => 'workforce_channel_unavailable'], 422);
        }

        $graph = $this->businessGraph->snapshot($tenantId);
        $knownSourceIds = array_unique(array_merge(
            array_map(static fn ($fact): string => $fact->sourceId, $graph->facts),
            array_map(static fn ($edge): string => $edge->sourceId, $graph->edges),
        ));
        $requestedSources = array_values(array_unique($validated['knowledge_source_ids'] ?? []));
        if (array_diff($requestedSources, $knownSourceIds) !== []) {
            return response()->json(['code' => 'workforce_knowledge_source_unavailable'], 422);
        }

        $role = AgentRole::from($validated['role_key']);
        $candidate = $this->candidate($tenantId, $role);
        $selectedChannels = $requestedChannelKeys === []
            ? $channels
            : array_values(array_filter($channels, static fn (array $channel): bool => in_array($channel['key'], $requestedChannelKeys, true)));
        $evidence = $candidate === null
            ? $this->pendingEvidence()
            : $this->evidence($tenantId, (string) $candidate->id, $market?->countryCode, $selectedChannels);
        $decision = $candidate === null ? null : $this->releaseDecision($tenantId, (string) $candidate->id);
        $simulations = array_values(array_unique($validated['simulation_case_ids'] ?? []));
        $state = $simulations !== [] ? 'simulation' : ($candidate === null ? 'preview' : ($evidence['verified'] ? 'live_evidence' : ($decision === null ? 'pending_evaluation' : 'pending_external_verification')));
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '') {
            $idempotencyKey = hash('sha256', $tenantId.'|'.json_encode($validated, JSON_THROW_ON_ERROR));
        }

        return response()->json([
            'data' => [
                'idempotency_key' => $idempotencyKey,
                'state' => $state,
                'role' => [
                    'key' => $role->value,
                    'candidate_agent_version_id' => $candidate?->id,
                    'candidate_status' => $candidate?->lifecycle_status ?? 'not_created',
                ],
                'approval' => [
                    'mode' => $validated['approval_mode'],
                    'release_decision_id' => $decision?->id,
                    'recorded' => $decision !== null,
                ],
                'simulation' => [
                    'enabled' => $simulations !== [],
                    'provider_label' => 'Simulation fixture — no external provider call',
                    'case_ids' => $simulations,
                ],
                'channels' => $selectedChannels,
                'knowledge' => [
                    'source_ids' => $requestedSources,
                    'evidence_label' => 'Business Graph sources',
                ],
                'live_evidence' => $evidence,
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    private function tenantId(): string
    {
        abort_unless($this->tenantContext->isInitialized() && ! $this->tenantContext->isRoot(), 403);

        return $this->tenantContext->id();
    }

    private function candidate(string $tenantId, AgentRole $role): ?object
    {
        return DB::table('ai_agent_versions')
            ->join('ai_agent_definitions', function ($join): void {
                $join->on('ai_agent_definitions.id', '=', 'ai_agent_versions.agent_definition_id')
                    ->on('ai_agent_definitions.tenant_id', '=', 'ai_agent_versions.tenant_id');
            })
            ->where('ai_agent_versions.tenant_id', $tenantId)
            ->orderByRaw("CASE ai_agent_versions.lifecycle_status WHEN 'active' THEN 0 WHEN 'eligible' THEN 1 WHEN 'candidate' THEN 2 ELSE 3 END")
            ->orderByDesc('ai_agent_versions.created_at')
            ->get(['ai_agent_versions.id', 'ai_agent_versions.configuration', 'ai_agent_versions.lifecycle_status', 'ai_agent_definitions.key as definition_key'])
            ->first(function (object $version) use ($role): bool {
                $configuration = $this->object($version->configuration);

                return AgentRoleCatalog::forAgentKey((string) ($configuration['workforce_role'] ?? $version->definition_key)) === $role;
            });
    }

    /** @return list<array<string, mixed>> */
    private function channels(string $tenantId): array
    {
        return DB::table('channel_connections')
            ->leftJoin('channel_provider_health', function ($join): void {
                $join->on('channel_provider_health.tenant_id', '=', 'channel_connections.tenant_id')
                    ->on('channel_provider_health.channel', '=', 'channel_connections.channel')
                    ->on('channel_provider_health.connection_key', '=', 'channel_connections.connection_key');
            })
            ->where('channel_connections.tenant_id', $tenantId)
            ->whereNull('channel_connections.deleted_at')
            ->get(['channel_connections.channel', 'channel_connections.connection_key', 'channel_provider_health.status as health_status', 'channel_provider_health.checked_at'])
            ->map(static fn (object $channel): array => [
                'key' => (string) $channel->connection_key,
                'channel' => (string) $channel->channel,
                'evidence_state' => (string) ($channel->health_status ?? 'pending_external_verification'),
                'checked_at' => $channel->checked_at,
            ])
            ->all();
    }

    /** @return array{verified: bool, state: string, label: string} */
    private function evidence(string $tenantId, string $agentVersionId, ?string $marketCode, array $channels): array
    {
        $registryVerified = false;
        try {
            $selection = $this->registry->resolve(new AiRegistryQuery(
                tenantId: $tenantId,
                audience: 'private',
                capability: 'private_tenant_agent',
                channel: 'web',
                marketCode: strtoupper((string) ($marketCode ?? '')),
                residencyClass: strtoupper((string) ($marketCode ?? '')),
                cohortSubject: $tenantId.'|workforce-activation',
            ));
            $registryVerified = hash_equals($agentVersionId, $selection->agentVersionId);
        } catch (AiExecutionDeniedException) {
            // A failed registry lookup is authoritative pending evidence, never a live fallback.
        }
        $channelVerified = collect($channels)->contains(static fn (array $channel): bool => $channel['evidence_state'] === 'available');
        $verified = $registryVerified && $channelVerified && $this->health->snapshot()->ready;

        return [
            'verified' => $verified,
            'state' => $verified ? 'live_evidence' : 'pending_external_verification',
            'label' => $verified ? 'Registry, health and channel evidence verified' : 'External verification is pending',
        ];
    }

    /** @return array{verified: false, state: string, label: string} */
    private function pendingEvidence(): array
    {
        return [
            'verified' => false,
            'state' => 'pending_external_verification',
            'label' => 'External verification is pending',
        ];
    }

    private function releaseDecision(string $tenantId, string $agentVersionId): ?object
    {
        return DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $agentVersionId)
            ->where('decision', 'passed')
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', now())
            ->where(static fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->latest('decided_at')
            ->first();
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }
}
