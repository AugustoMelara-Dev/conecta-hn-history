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
        $open = DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->whereNull('effective_until')
            ->get(['agent_version_id', 'previous_activation_id', 'rollout_percentage']);
        $agentVersionIds = $open->pluck('agent_version_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $fallbackIds = $open
            ->filter(static fn (object $activation): bool => (int) $activation->rollout_percentage < 100
                && is_string($activation->previous_activation_id)
                && Str::isUuid($activation->previous_activation_id))
            ->pluck('previous_activation_id')
            ->all();
        if ($fallbackIds !== []) {
            $agentVersionIds = [
                ...$agentVersionIds,
                ...DB::table('ai_agent_activations')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', $fallbackIds)
                    ->pluck('agent_version_id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all(),
            ];
        }
        $agentVersionIds = array_values(array_unique(array_filter(
            $agentVersionIds,
            static fn (string $id): bool => Str::isUuid($id),
        )));
        if ($versionType === 'agent' && in_array($versionId, $agentVersionIds, true)) {
            $this->deny();
        }
        if ($agentVersionIds === []) {
            return;
        }

        $promptVersionIds = [];
        $modelVersionIds = [];
        foreach (DB::table('ai_agent_versions')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $agentVersionIds)
            ->get(['model_version_id', 'configuration']) as $agent) {
            if (is_string($agent->model_version_id) && Str::isUuid($agent->model_version_id)) {
                $modelVersionIds[] = $agent->model_version_id;
            }
            $configuration = $this->jsonObject($agent->configuration);
            $promptVersionId = $configuration['prompt_version_id'] ?? null;
            if (is_string($promptVersionId) && Str::isUuid($promptVersionId)) {
                $promptVersionIds[] = $promptVersionId;
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
