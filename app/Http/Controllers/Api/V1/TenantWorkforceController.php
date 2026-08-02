<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Ai\Observability\AiFoundationHealth;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Application\BusinessGraph\Contracts\BusinessGraphRepositoryInterface;
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Access\Enums\Permission;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TenantWorkforceActivationRequest;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TenantWorkforceController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantMarketResolver $markets,
        private readonly BusinessGraphRepositoryInterface $businessGraph,
        private readonly AiFoundationHealth $health,
        private readonly AiRegistryResolver $registry,
        private readonly TenantPermissionGuardInterface $permissions,
    ) {}

    public function activate(TenantWorkforceActivationRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->whereKey($tenantId)->where('is_active', true)->firstOrFail();
        $user = $request->user();
        abort_unless(is_object($user), 401);
        abort_unless($this->permissions->allows($user, $tenant, Capability::AI_CONTENT, Permission::AI_USE), 403);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        $normalized = $this->normalizedRequest($validated);
        $requestHash = hash('sha256', $this->json($normalized));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'workforce:'.$tenantId.':'.$requestHash;
        }
        if (mb_strlen($idempotencyKey) > 160) {
            return response()->json(['code' => 'workforce_idempotency_key_invalid'], 422);
        }

        $existingRequest = DB::table('dovra_workforce_activation_requests')
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existingRequest !== null) {
            if (! hash_equals((string) $existingRequest->request_hash, $requestHash)) {
                return response()->json(['code' => 'workforce_idempotency_conflict'], 409);
            }

            return response()->json(['data' => $this->object($existingRequest->response)], 200, [], JSON_PRESERVE_ZERO_FRACTION);
        }

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
        $candidate = $this->ensureDraftCandidate($tenantId, $role);
        $selectedChannels = array_values(array_filter(
            $channels,
            static fn (array $channel): bool => in_array($channel['key'], $requestedChannelKeys, true),
        ));
        $evidence = $candidate === null
            ? $this->pendingEvidence()
            : $this->evidence($tenantId, (string) $candidate->id, $market?->countryCode, $selectedChannels);
        $decision = $candidate === null ? null : $this->releaseDecision($tenantId, (string) $candidate->id);
        $simulations = array_values(array_unique($validated['simulation_case_ids'] ?? []));
        $state = $simulations !== []
            ? 'simulation'
            : ($candidate === null
                ? 'preview'
                : ($evidence['verified'] ? 'live_evidence' : ($decision === null ? 'pending_evaluation' : 'pending_external_verification')));
        $requestId = (string) Str::uuid();
        $response = [
            'request_id' => $requestId,
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
                'provider_label' => 'Simulation fixture - no external provider call',
                'case_ids' => $simulations,
            ],
            'channels' => $selectedChannels,
            'knowledge' => [
                'source_ids' => $requestedSources,
                'evidence_label' => 'Business Graph sources',
            ],
            'live_evidence' => $evidence,
        ];

        try {
            $stored = DB::transaction(function () use (
                $tenantId,
                $idempotencyKey,
                $requestHash,
                $validated,
                $candidate,
                $decision,
                $state,
                $response,
                $user,
            ): ?array {
                // Serialize activation requests for this tenant so a pair of
                // concurrent requests cannot both pass the idempotency read.
                DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();

                $existing = DB::table('dovra_workforce_activation_requests')
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                        throw new HttpException(409, 'workforce_idempotency_conflict');
                    }

                    return $this->object($existing->response);
                }

                DB::table('dovra_workforce_activation_requests')->insert([
                    'id' => $response['request_id'],
                    'tenant_id' => $tenantId,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'role_key' => $validated['role_key'],
                    'approval_mode' => $validated['approval_mode'],
                    'channel_keys' => $this->json(array_values(array_unique($validated['channel_keys'] ?? []))),
                    'knowledge_source_ids' => $this->json(array_values(array_unique($validated['knowledge_source_ids'] ?? []))),
                    'simulation_case_ids' => $this->json(array_values(array_unique($validated['simulation_case_ids'] ?? []))),
                    'candidate_agent_version_id' => $candidate?->id,
                    'release_decision_id' => $decision?->id,
                    'requested_by' => is_object($user) && method_exists($user, 'getKey') ? (string) $user->getKey() : null,
                    'state' => $state,
                    'response' => $this->json($response),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return null;
            });
        } catch (HttpException $exception) {
            throw $exception;
        }

        return response()->json(['data' => $stored ?? $response], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    private function tenantId(): string
    {
        abort_unless($this->tenantContext->isInitialized() && ! $this->tenantContext->isRoot(), 403);

        return $this->tenantContext->id();
    }

    private function ensureDraftCandidate(string $tenantId, AgentRole $role): ?object
    {
        return DB::transaction(function () use ($tenantId, $role): ?object {
            DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();

            $candidate = $this->candidate($tenantId, $role);
            if ($candidate !== null) {
                return $candidate;
            }

            $configuration = AgentRoleCatalog::configuration($role);
            $definitionKey = 'tenant.'.$role->value;
            $definition = DB::table('ai_agent_definitions')
                ->where('tenant_id', $tenantId)
                ->where('key', $definitionKey)
                ->first();
            if ($definition === null) {
                $definitionId = (string) Str::uuid();
                DB::table('ai_agent_definitions')->insert([
                    'id' => $definitionId,
                    'tenant_id' => $tenantId,
                    'key' => $definitionKey,
                    'name' => 'Dovra '.ucwords(str_replace('_', ' ', $role->value)).' workforce role',
                    'visibility' => 'private',
                    'status' => 'active',
                    'metadata' => $this->json(['source' => 'dovra_workforce_activation']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $definition = DB::table('ai_agent_definitions')->where('id', $definitionId)->first();
            }
            if ($definition === null) {
                return null;
            }

            $draftConfiguration = [
                'workforce_role' => $role->value,
                'objective' => $configuration->objective,
                'allowed_jobs' => $configuration->allowedJobs,
                'risk_profile' => $configuration->riskProfile,
                'metric_keys' => $configuration->metricKeys,
                'source' => 'dovra_workforce_activation',
            ];
            $checksum = hash('sha256', $this->json([
                'definition_id' => (string) $definition->id,
                'configuration' => $draftConfiguration,
            ]));
            $version = DB::table('ai_agent_versions')
                ->where('agent_definition_id', $definition->id)
                ->where('checksum', $checksum)
                ->first();
            if ($version === null) {
                $versionId = (string) Str::uuid();
                DB::table('ai_agent_versions')->insert([
                    'id' => $versionId,
                    'tenant_id' => $tenantId,
                    'agent_definition_id' => $definition->id,
                    'model_version_id' => null,
                    'version' => ((int) DB::table('ai_agent_versions')->where('agent_definition_id', $definition->id)->max('version')) + 1,
                    'checksum' => $checksum,
                    'configuration' => $this->json($draftConfiguration),
                    'released_at' => null,
                    'lifecycle_status' => 'draft',
                    'kill_switch_enabled' => false,
                    'blocked_at' => null,
                    'blocked_by' => null,
                    'blocked_reason' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->candidate($tenantId, $role);
        }, 3);
    }

    private function candidate(string $tenantId, AgentRole $role): ?object
    {
        return DB::table('ai_agent_versions')
            ->join('ai_agent_definitions', function ($join): void {
                $join->on('ai_agent_definitions.id', '=', 'ai_agent_versions.agent_definition_id')
                    ->on('ai_agent_definitions.tenant_id', '=', 'ai_agent_versions.tenant_id');
            })
            ->where('ai_agent_versions.tenant_id', $tenantId)
            ->where('ai_agent_versions.lifecycle_status', 'draft')
            ->orderByRaw("CASE ai_agent_versions.lifecycle_status WHEN 'active' THEN 0 WHEN 'eligible' THEN 1 WHEN 'candidate' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END")
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
            ->get([
                'channel_connections.channel',
                'channel_connections.connection_key',
                'channel_connections.provider_key',
                'channel_connections.status as connection_status',
                'channel_connections.disconnected_at',
                'channel_provider_health.provider_key as health_provider_key',
                'channel_provider_health.status as health_status',
                'channel_provider_health.checked_at',
            ])
            ->map(fn (object $channel): array => [
                'key' => (string) $channel->channel.':'.(string) $channel->connection_key,
                'channel' => (string) $channel->channel,
                'connection_key' => (string) $channel->connection_key,
                'evidence_state' => $this->channelEvidenceAvailable($channel)
                    ? 'available'
                    : 'pending_external_verification',
                'checked_at' => $channel->checked_at,
            ])
            ->all();
    }

    private function channelEvidenceAvailable(object $channel): bool
    {
        if ((string) ($channel->connection_status ?? '') !== 'active'
            || $channel->disconnected_at !== null
            || (string) ($channel->health_status ?? '') !== 'available'
            || trim((string) ($channel->provider_key ?? '')) === ''
            || ! hash_equals(
                (string) $channel->provider_key,
                (string) ($channel->health_provider_key ?? ''),
            )
            || $channel->checked_at === null) {
            return false;
        }

        try {
            return CarbonImmutable::parse((string) $channel->checked_at)
                ->greaterThanOrEqualTo(now()->subSeconds((int) config('dovra_channels.contract.health.stale_after_seconds', 300)));
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{verified: bool, state: string, label: string} */
    private function evidence(string $tenantId, string $agentVersionId, ?string $marketCode, array $channels): array
    {
        if ($marketCode === null || $channels === []) {
            return $this->pendingEvidence();
        }

        $registryVerified = true;
        foreach ($channels as $channel) {
            try {
                $selection = $this->registry->resolve(new AiRegistryQuery(
                    tenantId: $tenantId,
                    audience: 'private',
                    capability: 'private_tenant_agent',
                    channel: strtolower((string) $channel['channel']),
                    marketCode: strtoupper($marketCode),
                    residencyClass: strtoupper($marketCode),
                    cohortSubject: $tenantId.'|workforce-activation|'.$channel['key'],
                ));
                if (! hash_equals($agentVersionId, $selection->agentVersionId)) {
                    $registryVerified = false;
                    break;
                }
            } catch (AiExecutionDeniedException) {
                $registryVerified = false;
                break;
            }
        }
        $channelVerified = collect($channels)->every(static fn (array $channel): bool => $channel['evidence_state'] === 'available');
        $verified = $registryVerified && $channelVerified && $this->health->snapshot()->ready;

        return [
            'verified' => $verified,
            'state' => $verified ? 'live_evidence' : 'pending_external_verification',
            'label' => $verified ? 'Registry, health and selected channel evidence verified' : 'External verification is pending',
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

    /** @param array<string, mixed> $validated */
    private function normalizedRequest(array $validated): array
    {
        $channels = array_values(array_unique(array_map('strval', $validated['channel_keys'] ?? [])));
        $sources = array_values(array_unique(array_map('strval', $validated['knowledge_source_ids'] ?? [])));
        $simulations = array_values(array_unique(array_map('strval', $validated['simulation_case_ids'] ?? [])));
        sort($channels);
        sort($sources);
        sort($simulations);

        return [
            'role_key' => (string) $validated['role_key'],
            'channel_keys' => $channels,
            'knowledge_source_ids' => $sources,
            'simulation_case_ids' => $simulations,
            'approval_mode' => (string) $validated['approval_mode'],
        ];
    }

    /** @param mixed $value */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }
}
