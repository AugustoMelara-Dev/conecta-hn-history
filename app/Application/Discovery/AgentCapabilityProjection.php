<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use App\Application\Ai\Registry\AiReleaseEligibility;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only projection of the capabilities that a tenant has actually
 * governed and activated. The AI registry and its release policy remain the
 * authority; Discover only exposes a safe, tenant-scoped view of that state.
 */
final class AgentCapabilityProjection
{
    public function __construct(
        private readonly AiReleaseEligibility $eligibility,
    ) {}

    /**
     * @return list<array{
     *     capability:string,
     *     agentKey:string,
     *     agentVersionId:string,
     *     agentVersion:int,
     *     role:?string,
     *     requiredCapabilities:list<string>,
     *     policyKey:string,
     *     policyChecksum:string,
     *     marketContextRequired:bool
     * }>
     */
    public function forTenant(string $tenantId): array
    {
        $tenantId = strtolower(trim($tenantId));
        if ($tenantId === '' || ! $this->registryTablesExist()) {
            return [];
        }

        // A capability is not public merely because an agent version exists.
        // It needs an explicit tenant market, an open activation, a governed
        // active version, and a currently published release policy/decision.
        if (! $this->hasPublishedTenantMarket($tenantId)) {
            return [];
        }

        $rows = DB::table('ai_agent_activations as activations')
            ->join('ai_agent_versions as versions', function ($join) use ($tenantId): void {
                $join->on('versions.id', '=', 'activations.agent_version_id')
                    ->where('versions.tenant_id', '=', $tenantId);
            })
            ->join('ai_agent_definitions as definitions', function ($join) use ($tenantId): void {
                $join->on('definitions.id', '=', 'versions.agent_definition_id')
                    ->where('definitions.tenant_id', '=', $tenantId);
            })
            ->join('ai_release_decisions as decisions', function ($join) use ($tenantId): void {
                $join->on('decisions.id', '=', 'activations.release_decision_id')
                    ->where('decisions.tenant_id', '=', $tenantId);
            })
            ->join('ai_release_policies as policies', function ($join) use ($tenantId): void {
                $join->on('policies.id', '=', 'decisions.release_policy_id')
                    ->where('policies.tenant_id', '=', $tenantId);
            })
            ->where('activations.tenant_id', $tenantId)
            ->where('activations.scope_type', 'tenant')
            ->where('activations.scope_key', $tenantId)
            ->whereNotNull('activations.release_decision_id')
            ->where('activations.effective_from', '<=', now())
            ->where(static function ($query): void {
                $query->whereNull('activations.effective_until')
                    ->orWhere('activations.effective_until', '>', now());
            })
            ->orderBy('definitions.key')
            ->orderByDesc('versions.version')
            ->get([
                'definitions.key as agent_key',
                'versions.id as agent_version_id',
                'versions.version as agent_version',
                'policies.key as policy_key',
                'policies.checksum as policy_checksum',
                'activations.release_decision_id',
            ]);

        $result = [];
        foreach ($rows as $row) {
            try {
                $candidate = $this->eligibility->assertExact(
                    tenantId: $tenantId,
                    decisionId: (string) $row->release_decision_id,
                    allowedLifecycleStatuses: ['active'],
                    denialReason: 'discovery_agent_capability',
                    expectedAgentVersionId: (string) $row->agent_version_id,
                );
            } catch (AiExecutionDeniedException) {
                continue;
            }

            $agentConfiguration = $candidate->agentConfiguration;
            $policyRules = $candidate->policyRules;
            $capability = trim((string) ($agentConfiguration['capability'] ?? ''));
            if ($capability === '') {
                continue;
            }

            $required = array_values(array_unique(array_merge(
                $this->stringList($agentConfiguration['required_capabilities'] ?? null),
                $this->stringList($policyRules['required_capabilities'] ?? null),
            )));
            if ($required === []) {
                continue;
            }

            $key = $capability.'|'.(string) $row->agent_version_id;
            $result[$key] = [
                'capability' => $capability,
                'agentKey' => (string) $row->agent_key,
                'agentVersionId' => (string) $row->agent_version_id,
                'agentVersion' => (int) $row->agent_version,
                'role' => isset($agentConfiguration['workforce_role'])
                    ? (string) $agentConfiguration['workforce_role']
                    : (isset($agentConfiguration['role']) ? (string) $agentConfiguration['role'] : null),
                'requiredCapabilities' => $required,
                'policyKey' => (string) $row->policy_key,
                'policyChecksum' => (string) $row->policy_checksum,
                'marketContextRequired' => true,
            ];
        }

        return array_values($result);
    }

    private function hasPublishedTenantMarket(string $tenantId): bool
    {
        return DB::table('tenant_markets as markets')
            ->join('market_pack_versions as versions', 'versions.id', '=', 'markets.market_pack_version_id')
            ->join('market_packs as packs', 'packs.id', '=', 'versions.market_pack_id')
            ->join('countries', 'countries.code', '=', 'packs.country_code')
            ->where('markets.tenant_id', $tenantId)
            ->where('markets.is_primary', true)
            ->where('packs.is_active', true)
            ->where('countries.is_active', true)
            ->where('versions.status', 'published')
            ->whereNotNull('versions.published_at')
            ->where('versions.published_at', '<=', now())
            ->where(static function ($query): void {
                $query->whereNull('versions.effective_from')
                    ->orWhere('versions.effective_from', '<=', now());
            })
            ->exists();
    }

    private function registryTablesExist(): bool
    {
        foreach ([
            'tenant_markets',
            'ai_agent_activations',
            'ai_agent_versions',
            'ai_agent_definitions',
            'ai_release_decisions',
            'ai_release_policies',
            'ai_evaluation_runs',
            'ai_evaluation_versions',
            'ai_evaluation_suites',
            'ai_evaluation_cases',
            'ai_evaluation_results',
            'ai_prompt_versions',
            'ai_prompt_definitions',
            'ai_model_versions',
            'ai_models',
            'ai_provider_versions',
            'ai_providers',
            'market_packs',
            'market_pack_versions',
            'countries',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
