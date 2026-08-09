<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiWorkforceDraftProvisioner
{
    public function ensure(string $tenantId, AgentRole $role): ?object
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
                DB::table('ai_agent_versions')->insert([
                    'id' => (string) Str::uuid(),
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
            ->orderByDesc('ai_agent_versions.created_at')
            ->get(['ai_agent_versions.id', 'ai_agent_versions.configuration', 'ai_agent_versions.lifecycle_status', 'ai_agent_definitions.key as definition_key'])
            ->first(function (object $version) use ($role): bool {
                $configuration = $this->object($version->configuration);

                return AgentRoleCatalog::forAgentKey((string) ($configuration['workforce_role'] ?? $version->definition_key)) === $role;
            });
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
