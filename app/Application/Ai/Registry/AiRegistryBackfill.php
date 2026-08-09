<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiRegistryBackfill
{
    private int $gaps = 0;

    private int $conflicts = 0;

    /** @var array<string, string> */
    private array $modelVersionIds = [];

    public function __construct(
        private readonly AiRegistryChecksum $checksum,
        private readonly LaravelAiContentCipher $cipher,
        private readonly AiRegistryLockCoordinator $locks,
    ) {}

    public function run(): AiRegistryBackfillReport
    {
        $this->gaps = 0;
        $this->conflicts = 0;
        $this->modelVersionIds = [];

        foreach ((array) config('ai.providers', []) as $providerKey => $definition) {
            if (! is_string($providerKey) || ! is_array($definition) || ! ($definition['enabled'] ?? false)) {
                continue;
            }

            $this->backfillProvider($providerKey, $definition);
        }

        $capabilities = (array) config('ai.capabilities', []);
        $public = $capabilities['public_discovery'] ?? null;
        if (is_array($public) && ($public['enabled'] ?? false)) {
            $tenantId = $this->publicTenantId();
            if ($tenantId !== null) {
                $this->backfillAgentFamily(
                    tenantId: $tenantId,
                    capability: 'public_discovery',
                    definition: $public,
                    agentKey: 'public.discovery',
                    visibility: 'platform_public',
                    promptKey: 'public.discovery.system',
                    promptName: 'Public discovery system prompt',
                    prompt: $this->prompt('public_discovery'),
                );
            }
        }

        $private = $capabilities['private_tenant_agent'] ?? null;
        if (is_array($private) && ($private['enabled'] ?? false) && (bool) config('ai.private.enabled', false)) {
            $allowlist = (array) config('ai.private.tenant_allowlist', []);
            foreach (array_count_values(array_map('strval', $allowlist)) as $tenantId => $occurrences) {
                if ($occurrences > 1) {
                    $this->conflicts++;

                    continue;
                }

                if (! Str::isUuid($tenantId) || ! DB::table('tenants')->where('id', $tenantId)->where('is_active', true)->exists()) {
                    $this->gaps++;

                    continue;
                }

                $this->backfillAgentFamily(
                    tenantId: $tenantId,
                    capability: 'private_tenant_agent',
                    definition: $private,
                    agentKey: 'tenant.private_assistant',
                    visibility: 'tenant_private',
                    promptKey: 'tenant.private_assistant.system',
                    promptName: 'Tenant private assistant system prompt',
                    prompt: $this->prompt('private_tenant_agent'),
                );
            }
        }

        return new AiRegistryBackfillReport(
            providers: DB::table('ai_providers')->count(),
            providerVersions: DB::table('ai_provider_versions')->count(),
            models: DB::table('ai_models')->count(),
            modelVersions: DB::table('ai_model_versions')->count(),
            agentDefinitions: DB::table('ai_agent_definitions')->count(),
            agentVersions: DB::table('ai_agent_versions')->count(),
            promptDefinitions: DB::table('ai_prompt_definitions')->count(),
            promptVersions: DB::table('ai_prompt_versions')->count(),
            activations: DB::table('ai_agent_activations')->count(),
            gaps: $this->gaps,
            conflicts: $this->conflicts,
        );
    }

    /** @param array<string, mixed> $definition */
    private function backfillProvider(string $providerKey, array $definition): void
    {
        DB::transaction(function () use ($providerKey, $definition): void {
            $adapterKey = (string) ($definition['adapter_key'] ?? $providerKey.'_structured_text');
            $secretReference = (string) ($definition['secret_reference'] ?? config('ai.registry.openai_secret_reference', ''));
            if ($adapterKey === '' || $secretReference === '') {
                $this->gaps++;

                return;
            }

            $provider = DB::table('ai_providers')->where('provider_key', $providerKey)->first();
            if ($provider !== null && $provider->adapter_key !== $adapterKey) {
                $this->conflicts++;

                return;
            }

            $providerId = $provider?->id ?? (string) Str::uuid();
            if ($provider === null) {
                DB::table('ai_providers')->insert([
                    'id' => $providerId,
                    'provider_key' => $providerKey,
                    'adapter_key' => $adapterKey,
                    'default_secret_reference' => $secretReference,
                    'name' => strtoupper($providerKey),
                    'status' => 'active',
                    'metadata' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($provider->default_secret_reference !== $secretReference) {
                DB::table('ai_providers')->where('id', $providerId)->update([
                    'default_secret_reference' => $secretReference,
                    'updated_at' => now(),
                ]);
            }

            $providerConfiguration = [
                'enabled' => true,
                'adapter_key' => $adapterKey,
                ...$this->routingConfiguration($definition),
            ];
            $providerPayload = [
                'provider_key' => $providerKey,
                'adapter_key' => $adapterKey,
                'secret_reference' => $secretReference,
                'configuration' => $providerConfiguration,
            ];
            $providerChecksum = $this->checksum->for($providerPayload);
            $providerVersion = DB::table('ai_provider_versions')
                ->where('provider_id', $providerId)
                ->where('checksum', $providerChecksum)
                ->first();
            $providerVersionId = $providerVersion?->id ?? (string) Str::uuid();
            if ($providerVersion === null) {
                DB::table('ai_provider_versions')->insert([
                    'id' => $providerVersionId,
                    'provider_id' => $providerId,
                    'version' => $this->nextVersion('ai_provider_versions', 'provider_id', $providerId),
                    'checksum' => $providerChecksum,
                    'secret_reference' => $secretReference,
                    'status' => 'active',
                    'configuration' => $this->json($providerConfiguration),
                    'released_at' => null,
                    'lifecycle_status' => 'draft',
                    'kill_switch_enabled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ((array) ($definition['models'] ?? []) as $modelKey => $modelDefinition) {
                if (is_string($modelKey) && is_array($modelDefinition)) {
                    $this->backfillModel($providerId, $providerVersionId, $providerKey, $modelKey, $modelDefinition);
                }
            }
        });
    }

    /** @param array<string, mixed> $definition */
    private function backfillModel(string $providerId, string $providerVersionId, string $providerKey, string $modelKey, array $definition): void
    {
        $model = DB::table('ai_models')->where('provider_id', $providerId)->where('key', $modelKey)->first();
        $modelId = $model?->id ?? (string) Str::uuid();
        if ($model === null) {
            DB::table('ai_models')->insert([
                'id' => $modelId,
                'provider_id' => $providerId,
                'key' => $modelKey,
                'name' => $modelKey,
                'status' => 'active',
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $configuration = [
            'context_window' => isset($definition['context_window']) ? (int) $definition['context_window'] : null,
            'input_usd_per_million' => (float) ($definition['input_usd_per_million'] ?? 0),
            'output_usd_per_million' => (float) ($definition['output_usd_per_million'] ?? 0),
            'currency' => (string) ($definition['currency'] ?? 'USD'),
            ...$this->routingConfiguration($definition),
        ];
        $payload = [
            'provider_version_id' => $providerVersionId,
            'model_key' => $modelKey,
            'configuration' => $configuration,
        ];
        $checksum = $this->checksum->for($payload);
        $version = DB::table('ai_model_versions')->where('model_id', $modelId)->where('checksum', $checksum)->first();
        $versionId = $version?->id ?? (string) Str::uuid();
        if ($version === null) {
            DB::table('ai_model_versions')->insert([
                'id' => $versionId,
                'model_id' => $modelId,
                'provider_version_id' => $providerVersionId,
                'version' => $this->nextVersion('ai_model_versions', 'model_id', $modelId),
                'checksum' => $checksum,
                'context_window' => $configuration['context_window'],
                'input_cost_minor' => $this->minor($configuration['input_usd_per_million']),
                'output_cost_minor' => $this->minor($configuration['output_usd_per_million']),
                'currency' => $configuration['currency'],
                'configuration' => $this->json($configuration),
                'released_at' => null,
                'lifecycle_status' => 'draft',
                'kill_switch_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->modelVersionIds[$providerKey.':'.$modelKey] = $versionId;
    }

    /** @param array<string, mixed> $definition */
    private function backfillAgentFamily(
        string $tenantId,
        string $capability,
        array $definition,
        string $agentKey,
        string $visibility,
        string $promptKey,
        string $promptName,
        string $prompt,
    ): void {
        $workforceRole = AgentRoleCatalog::forAgentKey($agentKey);
        if ($workforceRole === null) {
            $this->gaps++;

            return;
        }

        $provider = $definition['provider'] ?? null;
        $models = is_array($definition['models'] ?? null)
            ? array_values($definition['models'])
            : [];
        if (! is_string($provider) || $models === []) {
            $this->gaps++;

            return;
        }

        $modelVersionIds = [];
        foreach ($models as $model) {
            if (! is_string($model) || ! isset($this->modelVersionIds[$provider.':'.$model])) {
                $this->gaps++;

                return;
            }

            $modelVersionIds[] = $this->modelVersionIds[$provider.':'.$model];
        }

        $model = $models[0];

        DB::transaction(function () use ($tenantId, $capability, $definition, $agentKey, $visibility, $promptKey, $promptName, $prompt, $provider, $model, $models, $modelVersionIds, $workforceRole): void {
            $this->locks->tenant($tenantId);
            $promptVersion = $this->ensurePrompt($tenantId, $promptKey, $promptName, $visibility, $prompt);
            if ($promptVersion === null) {
                return;
            }

            $agent = $this->ensureDefinition('ai_agent_definitions', $tenantId, $agentKey, $agentKey, $visibility);
            if ($agent === null) {
                return;
            }

            $modelVersionId = $modelVersionIds[0];
            $configuration = [
                'capability' => $capability,
                'audience' => $visibility === 'platform_public' ? 'public' : 'private',
                'workforce_role' => $workforceRole->value,
                'channels' => array_values(array_map(
                    'strval',
                    (array) ($definition['channels'] ?? ['web']),
                )),
                'provider' => $provider,
                'model' => $model,
                'model_version_id' => $modelVersionId,
                'models' => $models,
                'model_version_ids' => $modelVersionIds,
                'prompt_key' => $promptKey,
                'prompt_version_id' => $promptVersion['id'],
                'output_schema' => (string) ($definition['output_schema'] ?? match ($capability) {
                    'public_discovery' => 'public_discovery.answer.v1',
                    default => 'private_agent.answer.v1',
                }),
                'required_capabilities' => array_values(array_map(
                    'strval',
                    (array) ($definition['required_capabilities'] ?? ['structured_output']),
                )),
                'max_output_tokens' => max(1, (int) ($definition['max_output_tokens'] ?? 640)),
                'tools' => array_values(array_map('strval', (array) ($definition['tools'] ?? []))),
            ];
            $checksum = $this->checksum->for([
                'agent_key' => $agentKey,
                'configuration' => $configuration,
                'prompt_checksum' => $promptVersion['checksum'],
            ]);
            $version = DB::table('ai_agent_versions')->where('agent_definition_id', $agent->id)->where('checksum', $checksum)->first();
            $versionId = $version?->id ?? (string) Str::uuid();
            if ($version === null) {
                DB::table('ai_agent_versions')->insert([
                    'id' => $versionId,
                    'tenant_id' => $tenantId,
                    'agent_definition_id' => $agent->id,
                    'model_version_id' => $modelVersionId,
                    'version' => $this->nextVersion('ai_agent_versions', 'agent_definition_id', $agent->id),
                    'checksum' => $checksum,
                    'configuration' => $this->json($configuration),
                    'released_at' => null,
                    'lifecycle_status' => 'draft',
                    'kill_switch_enabled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->ensureCompatibilityPolicy($tenantId);
            $this->ensureActivation($tenantId, $capability, $versionId);
        });
    }

    /** @return array{id:string, checksum:string}|null */
    private function ensurePrompt(string $tenantId, string $key, string $name, string $visibility, string $template): ?array
    {
        $definition = $this->ensureDefinition('ai_prompt_definitions', $tenantId, $key, $name, $visibility);
        if ($definition === null) {
            return null;
        }

        $templateHash = hash('sha256', $template);
        $keyVersion = strtolower(trim((string) config(
            'ai.execution_retention.encryption_key_version',
            'v1',
        )));
        $checksum = $this->checksum->for([
            'prompt_key' => $key,
            'template_hash' => $templateHash,
            'input_schema' => [],
            'encryption_key_version' => $keyVersion,
        ]);
        $version = DB::table('ai_prompt_versions')->where('prompt_definition_id', $definition->id)->where('checksum', $checksum)->first();
        if ($version !== null) {
            return ['id' => $version->id, 'checksum' => $checksum];
        }

        $versionId = (string) Str::uuid();
        $encrypted = $this->cipher->encrypt($tenantId, $versionId, 'prompt_template', $template);
        DB::table('ai_prompt_versions')->insert([
            'id' => $versionId,
            'tenant_id' => $tenantId,
            'prompt_definition_id' => $definition->id,
            'version' => $this->nextVersion('ai_prompt_versions', 'prompt_definition_id', $definition->id),
            'checksum' => $checksum,
            'template_ciphertext' => $encrypted->ciphertext,
            'template_hash' => $encrypted->contentHash,
            'encryption_key_version' => $encrypted->keyVersion,
            'input_schema' => $this->json([]),
            'released_at' => null,
            'lifecycle_status' => 'draft',
            'kill_switch_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $versionId, 'checksum' => $checksum];
    }

    private function ensureDefinition(string $table, string $tenantId, string $key, string $name, string $visibility): ?object
    {
        $definition = DB::table($table)->where('tenant_id', $tenantId)->where('key', $key)->first();
        if ($definition !== null) {
            if ($definition->visibility !== $visibility || $definition->name !== $name) {
                $this->conflicts++;

                return null;
            }

            return $definition;
        }

        $id = (string) Str::uuid();
        DB::table($table)->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'key' => $key,
            'name' => $name,
            'visibility' => $visibility,
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table($table)->where('id', $id)->first();
    }

    private function ensureCompatibilityPolicy(string $tenantId): void
    {
        $rules = [
            'compatibility' => 'bootstrap_v2',
            'requires_evaluation' => true,
            'required_capabilities' => ['structured_output'],
            'allowed_market_country_codes' => $this->defaultCountryCodes(
                'ai.registry.default_market_country_codes',
            ),
            'residency_country_codes' => $this->defaultCountryCodes(
                'ai.registry.default_residency_country_codes',
            ),
        ];
        $checksum = $this->checksum->for($rules);
        $policy = DB::table('ai_release_policies')
            ->where('tenant_id', $tenantId)
            ->where('key', 'compatibility.default')
            ->lockForUpdate()
            ->first();
        if ($policy !== null) {
            if ($policy->checksum !== $checksum) {
                $this->conflicts++;
            }

            return;
        }

        DB::table('ai_release_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'key' => 'compatibility.default',
            'name' => 'Bootstrap compatibility policy',
            'checksum' => $checksum,
            'rules' => $this->json($rules),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureActivation(string $tenantId, string $capability, string $agentVersionId): void
    {
        $openActivations = $this->locks->activationScope($tenantId, 'capability', $capability);
        if ($openActivations->count() > 1) {
            $this->conflicts++;

            return;
        }

        if ($openActivations->isNotEmpty()) {
            return;
        }

        DB::table('ai_agent_activations')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'scope_type' => 'capability',
            'scope_key' => $capability,
            'agent_version_id' => $agentVersionId,
            'rollout_percentage' => 100,
            'cohort_salt' => hash('sha256', $tenantId.'|'.$capability),
            'release_decision_id' => null,
            'previous_activation_id' => null,
            'effective_from' => now(),
            'effective_until' => null,
            'activated_by' => 'registry_backfill',
            'reason' => 'Program 05 registry bootstrap',
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function publicTenantId(): ?string
    {
        $tenantId = config('ai.public.billing_tenant_id');
        if (! is_string($tenantId) || ! Str::isUuid($tenantId) || ! DB::table('tenants')->where('id', $tenantId)->exists()) {
            $this->gaps++;

            return null;
        }

        return $tenantId;
    }

    private function prompt(string $key): string
    {
        $prompt = config("ai.registry.prompts.{$key}");
        if (! is_string($prompt) || trim($prompt) === '') {
            throw new \LogicException("AI registry prompt [{$key}] is not configured.");
        }

        return trim($prompt);
    }

    private function nextVersion(string $table, string $ownerColumn, string $ownerId): int
    {
        return ((int) DB::table($table)->where($ownerColumn, $ownerId)->max('version')) + 1;
    }

    private function minor(float $usd): int
    {
        return max(0, (int) round($usd * 100));
    }

    /** @param array<string, mixed> $definition @return array<string, list<string>> */
    private function routingConfiguration(array $definition): array
    {
        return [
            'capabilities' => array_values(array_map(
                'strval',
                (array) ($definition['capabilities'] ?? ['structured_output']),
            )),
            'allowed_market_country_codes' => array_values(array_map(
                'strval',
                (array) ($definition['allowed_market_country_codes']
                    ?? $this->defaultCountryCodes('ai.registry.default_market_country_codes')),
            )),
            'residency_country_codes' => array_values(array_map(
                'strval',
                (array) ($definition['residency_country_codes']
                    ?? $this->defaultCountryCodes('ai.registry.default_residency_country_codes')),
            )),
        ];
    }

    /** @return list<string> */
    private function defaultCountryCodes(string $configurationKey): array
    {
        $countries = array_values(array_filter(
            array_map('strval', (array) config($configurationKey, ['HN'])),
            static fn (string $country): bool => preg_match('/^[A-Z]{2}$/', $country) === 1,
        ));

        return $countries === [] ? ['HN'] : array_values(array_unique($countries));
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
