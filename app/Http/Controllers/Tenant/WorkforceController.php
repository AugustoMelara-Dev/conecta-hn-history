<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Ai\Observability\AiFoundationHealth;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Application\BusinessGraph\Contracts\BusinessGraphRepositoryInterface;
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Access\Enums\Permission;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class WorkforceController extends Controller
{
    public function show(
        Request $request,
        TenantContext $tenantContext,
        TenantPermissionGuardInterface $permissions,
        TenantMarketResolver $markets,
        BusinessGraphRepositoryInterface $businessGraph,
        AiFoundationHealth $health,
        AiRegistryResolver $registry,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->whereKey((string) $tenantContext->id())->where('is_active', true)->firstOrFail();
        abort_unless($permissions->allows($request->user(), $tenant, Capability::AI_CONTENT, Permission::AI_USE), 403);

        $market = $markets->resolveOrNull($tenant);
        $channels = $this->channels((string) $tenant->getKey());
        $roles = $this->roles((string) $tenant->getKey(), $channels, $market?->countryCode, $health, $registry);
        $graph = $businessGraph->snapshot((string) $tenant->getKey());
        $healthSnapshot = $health->snapshot();

        return Inertia::render('Tenant/Workforce', [
            'workforce' => [
                'tenant' => [
                    'id' => (string) $tenant->getKey(),
                    'name' => (string) $tenant->name,
                    'market' => $market === null ? null : [
                        'country_code' => $market->countryCode,
                        'locale_code' => $market->localeCode,
                        'currency_code' => $market->currencyCode,
                        'timezone' => $market->timezone,
                    ],
                ],
                'roles' => $roles,
                'channels' => $channels,
                'setup' => [
                    'business_graph_facts' => count($graph->facts),
                    'business_graph_edges' => count($graph->edges),
                    'registry_status' => $healthSnapshot->registryStatus,
                    'last_successful_evaluation_at' => $healthSnapshot->lastSuccessfulEvaluationAt,
                ],
                'first_outcomes' => [
                    'lead_qualification',
                    'quote',
                    'reservation',
                    'support_resolution',
                    'task_completion',
                ],
                'live_evidence' => [
                    'verified' => false,
                    'state' => 'pending_external_verification',
                    'label' => 'External verification is pending',
                ],
                'activation_url' => '/api/v1/tenant/workforce/activate',
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function roles(
        string $tenantId,
        array $channels,
        ?string $marketCode,
        AiFoundationHealth $health,
        AiRegistryResolver $registry,
    ): array {
        $records = DB::table('ai_agent_versions')
            ->join('ai_agent_definitions', function ($join): void {
                $join->on('ai_agent_definitions.id', '=', 'ai_agent_versions.agent_definition_id')
                    ->on('ai_agent_definitions.tenant_id', '=', 'ai_agent_versions.tenant_id');
            })
            ->where('ai_agent_versions.tenant_id', $tenantId)
            ->select([
                'ai_agent_versions.id', 'ai_agent_versions.configuration', 'ai_agent_versions.lifecycle_status',
                'ai_agent_versions.created_at', 'ai_agent_definitions.key as definition_key',
            ])
            ->get();

        $byRole = [];
        foreach ($records as $record) {
            $configuration = $this->object($record->configuration);
            $role = AgentRoleCatalog::forAgentKey((string) ($configuration['workforce_role'] ?? $record->definition_key));
            if ($role === null || isset($byRole[$role->value])) {
                continue;
            }

            $byRole[$role->value] = $record;
        }

        $result = [];
        foreach ($byRole as $roleKey => $record) {
            $role = AgentRoleCatalog::forAgentKey($roleKey);
            if ($role === null) {
                continue;
            }
            $configuration = AgentRoleCatalog::configuration($role);
            $evidence = $this->evidence(
                $tenantId,
                (string) $record->id,
                $marketCode,
                $channels,
                $health,
                $registry,
            );
            $decision = $this->releaseDecision($tenantId, (string) $record->id);
            $state = $evidence['verified'] ? 'live_evidence' : ($decision === null ? 'pending_evaluation' : 'pending_external_verification');

            $result[] = [
                'key' => $role->value,
                'title' => ucfirst(str_replace('_', ' ', $role->value)),
                'objective' => $configuration->objective,
                'risk_profile' => $configuration->riskProfile,
                'metric_keys' => $configuration->metricKeys,
                'agent_version_id' => (string) $record->id,
                'candidate_status' => (string) $record->lifecycle_status,
                'state' => $state,
                'live_evidence' => $evidence,
            ];
        }

        return array_values($result);
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
            ->orderBy('channel_connections.channel')
            ->get([
                'channel_connections.channel',
                'channel_connections.connection_key',
                'channel_connections.provider_key',
                'channel_connections.status',
                'channel_connections.disconnected_at',
                'channel_provider_health.provider_key as health_provider_key',
                'channel_provider_health.status as health_status',
                'channel_provider_health.checked_at',
            ])
            ->map(fn (object $channel): array => [
                'key' => (string) $channel->channel.':'.(string) $channel->connection_key,
                'channel' => (string) $channel->channel,
                'connection_key' => (string) $channel->connection_key,
                'connection_status' => (string) $channel->status,
                'evidence_state' => $this->channelEvidenceAvailable($channel)
                    ? 'available'
                    : 'pending_external_verification',
                'checked_at' => $channel->checked_at,
            ])
            ->all();
    }

    private function channelEvidenceAvailable(object $channel): bool
    {
        if ((string) ($channel->status ?? '') !== 'active'
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
    private function evidence(string $tenantId, string $agentVersionId, ?string $marketCode, array $channels, AiFoundationHealth $health, AiRegistryResolver $registry): array
    {
        if ($marketCode === null || $channels === []) {
            return [
                'verified' => false,
                'state' => 'pending_external_verification',
                'label' => 'External verification is pending',
            ];
        }

        $registryVerified = true;
        foreach ($channels as $channel) {
            try {
                $selection = $registry->resolve(new AiRegistryQuery(
                    tenantId: $tenantId,
                    audience: 'private',
                    capability: 'private_tenant_agent',
                    channel: strtolower((string) $channel['channel']),
                    marketCode: strtoupper($marketCode),
                    residencyClass: strtoupper($marketCode),
                    cohortSubject: $tenantId.'|workforce-cockpit|'.$channel['key'],
                ));
                if (! hash_equals($agentVersionId, $selection->agentVersionId)) {
                    $registryVerified = false;
                    break;
                }
            } catch (AiExecutionDeniedException) {
                // A registry denial is evidence that the cockpit must stay pending.
                $registryVerified = false;
                break;
            }
        }

        $channelVerified = collect($channels)->every(
            static fn (array $channel): bool => $channel['evidence_state'] === 'available',
        );
        $verified = $registryVerified && $channelVerified && $health->snapshot()->ready;

        return [
            'verified' => $verified,
            'state' => $verified ? 'live_evidence' : 'pending_external_verification',
            'label' => $verified ? 'Registry, health and selected channel evidence verified' : 'External verification is pending',
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
