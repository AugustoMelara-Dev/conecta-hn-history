<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AiRegistryRetirementGuard
{
    public function assertNotReferenced(
        string $tenantId,
        string $versionType,
        string $versionId,
    ): void {
        $globalVersion = in_array($versionType, ['model', 'provider'], true);
        $openQuery = DB::table('ai_agent_activations')
            ->whereNull('effective_until');
        if (! $globalVersion) {
            $openQuery->where('tenant_id', $tenantId);
        }
        $open = $openQuery->get([
            'id',
            'tenant_id',
            'agent_version_id',
            'previous_activation_id',
            'rollout_percentage',
        ]);
        $agentVersionIdsByTenant = [];
        $fallbackIdsByTenant = [];
        foreach ($open as $activation) {
            $activationTenantId = (string) $activation->tenant_id;
            $agentVersionId = (string) $activation->agent_version_id;
            if (Str::isUuid($activationTenantId) && Str::isUuid($agentVersionId)) {
                $agentVersionIdsByTenant[$activationTenantId][] = $agentVersionId;
            }
            if ((int) $activation->rollout_percentage < 100
                && is_string($activation->previous_activation_id)
                && Str::isUuid($activation->previous_activation_id)) {
                $fallbackIdsByTenant[$activationTenantId][] = $activation->previous_activation_id;
            }
        }
        foreach ($fallbackIdsByTenant as $activationTenantId => $fallbackIds) {
            $visitedActivationIds = [];
            while ($fallbackIds !== []) {
                $fallbackIds = array_values(array_diff(
                    array_unique($fallbackIds),
                    $visitedActivationIds,
                ));
                if ($fallbackIds === []) {
                    break;
                }
                $visitedActivationIds = [...$visitedActivationIds, ...$fallbackIds];
                $fallbackIds = [];
                foreach (DB::table('ai_agent_activations')
                    ->where('tenant_id', $activationTenantId)
                    ->whereIn('id', $visitedActivationIds)
                    ->get(['id', 'agent_version_id', 'previous_activation_id']) as $activation) {
                    $agentVersionId = (string) $activation->agent_version_id;
                    if (Str::isUuid($agentVersionId)) {
                        $agentVersionIdsByTenant[$activationTenantId][] = $agentVersionId;
                    }
                    if (is_string($activation->previous_activation_id)
                        && Str::isUuid($activation->previous_activation_id)
                        && ! in_array($activation->previous_activation_id, $visitedActivationIds, true)) {
                        $fallbackIds[] = $activation->previous_activation_id;
                    }
                }
            }
        }
        foreach ($agentVersionIdsByTenant as &$agentVersionIds) {
            $agentVersionIds = array_values(array_unique($agentVersionIds));
        }
        unset($agentVersionIds);

        $tenantAgentVersionIds = $agentVersionIdsByTenant[$tenantId] ?? [];
        if ($versionType === 'agent' && in_array($versionId, $tenantAgentVersionIds, true)) {
            $this->deny();
        }
        if ($agentVersionIdsByTenant === []) {
            return;
        }

        $promptVersionIds = [];
        $modelVersionIds = [];
        foreach ($agentVersionIdsByTenant as $activationTenantId => $agentVersionIds) {
            foreach (DB::table('ai_agent_versions')
                ->where('tenant_id', $activationTenantId)
                ->whereIn('id', $agentVersionIds)
                ->get(['model_version_id', 'configuration']) as $agent) {
                if (is_string($agent->model_version_id) && Str::isUuid($agent->model_version_id)) {
                    $modelVersionIds[] = $agent->model_version_id;
                }
                $configuration = $this->jsonObject($agent->configuration);
                if ($activationTenantId === $tenantId) {
                    $promptVersionId = $configuration['prompt_version_id'] ?? null;
                    if (is_string($promptVersionId) && Str::isUuid($promptVersionId)) {
                        $promptVersionIds[] = $promptVersionId;
                    }
                }
                $configuredModels = $configuration['model_version_ids'] ?? null;
                if (is_array($configuredModels)) {
                    foreach ($configuredModels as $modelVersionId) {
                        if (is_string($modelVersionId) && Str::isUuid($modelVersionId)) {
                            $modelVersionIds[] = $modelVersionId;
                        }
                    }
                }
            }
        }
        $promptVersionIds = array_values(array_unique($promptVersionIds));
        $modelVersionIds = array_values(array_unique($modelVersionIds));
        if ($versionType === 'prompt' && in_array($versionId, $promptVersionIds, true)) {
            $this->deny();
        }
        if ($versionType === 'model' && in_array($versionId, $modelVersionIds, true)) {
            $this->deny();
        }
        if ($versionType === 'provider' && $modelVersionIds !== []) {
            $providerVersionIds = DB::table('ai_model_versions')
                ->whereIn('id', $modelVersionIds)
                ->pluck('provider_version_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
            if (in_array($versionId, $providerVersionIds, true)) {
                $this->deny();
            }
        }
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    private function deny(): never
    {
        throw ValidationException::withMessages([
            'target_status' => [
                'A registry version referenced by an open assignment or canary fallback cannot be retired.',
            ],
        ]);
    }
}
