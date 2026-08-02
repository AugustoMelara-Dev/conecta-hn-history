<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only projection of the capabilities that a tenant has actually
 * governed and activated. The AI registry and its release policy remain the
 * authority; Discover only exposes a safe, tenant-scoped view of that state.
 */
final class AgentCapabilityProjection
{
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
        if (! DB::table('tenant_markets')
            ->where('tenant_id', $tenantId)
            ->where('is_primary', true)
            ->exists()) {
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
            ->whereNotNull('activations.release_decision_id')
            ->where('activations.effective_from', '<=', now())
            ->where(static function ($query): void {
                $query->whereNull('activations.effective_until')
                    ->orWhere('activations.effective_until', '>', now());
            })
            ->whereIn('decisions.decision', ['passed', 'approved', 'eligible', 'released', 'published'])
            ->whereNull('decisions.revoked_at')
            ->where(static function ($query): void {
                $query->whereNull('decisions.valid_from')->orWhere('decisions.valid_from', '<=', now());
            })
            ->where(static function ($query): void {
                $query->whereNull('decisions.valid_until')->orWhere('decisions.valid_until', '>', now());
            })
            ->whereIn('policies.status', ['active', 'published'])
            ->whereIn('definitions.status', ['active', 'published'])
            ->whereIn('versions.lifecycle_status', ['active', 'eligible'])
            ->where('versions.kill_switch_enabled', false)
            ->orderBy('definitions.key')
            ->orderByDesc('versions.version')
            ->get([
                'definitions.key as agent_key',
                'versions.id as agent_version_id',
                'versions.version as agent_version',
                'versions.configuration as agent_configuration',
                'policies.key as policy_key',
                'policies.checksum as policy_checksum',
                'policies.rules as policy_rules',
            ]);

        $result = [];
        foreach ($rows as $row) {
            $agentConfiguration = $this->jsonObject($row->agent_configuration);
            $policyRules = $this->jsonObject($row->policy_rules);
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

    private function registryTablesExist(): bool
    {
        foreach ([
            'tenant_markets',
            'ai_agent_activations',
            'ai_agent_versions',
            'ai_agent_definitions',
            'ai_release_decisions',
            'ai_release_policies',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
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
