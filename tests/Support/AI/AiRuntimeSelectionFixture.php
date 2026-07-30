<?php

declare(strict_types=1);

namespace Tests\Support\AI;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiRuntimeSelectionFixture
{
    public static function create(
        string $tenantId,
        string $providerKey = 'fake',
        string $adapterKey = 'fake_structured_text',
        string $modelKey = 'fake-1',
        string $systemPrompt = 'Use authorized evidence only.',
        string $outputSchema = 'private_agent.answer.v1',
        int $maxOutputTokens = 800,
        string $marketCountryCode = 'HN',
        string $visibility = 'tenant_private',
    ): AiRuntimeSelection {
        $provider = DB::table('ai_providers')->where('provider_key', $providerKey)->first();
        if ($provider === null) {
            $providerId = (string) Str::uuid();
            $providerVersionId = (string) Str::uuid();
            $modelId = (string) Str::uuid();
            $modelVersionId = (string) Str::uuid();
            DB::table('ai_providers')->insert([
                'id' => $providerId,
                'provider_key' => $providerKey,
                'adapter_key' => $adapterKey,
                'default_secret_reference' => $providerKey.'.primary',
                'name' => ucfirst($providerKey),
                'status' => 'active',
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ai_provider_versions')->insert([
                'id' => $providerVersionId,
                'provider_id' => $providerId,
                'version' => 1,
                'checksum' => hash('sha256', $providerVersionId),
                'secret_reference' => $providerKey.'.primary',
                'status' => 'active',
                'configuration' => json_encode([
                    'enabled' => true,
                    'capabilities' => ['structured_output'],
                    'allowed_market_country_codes' => [$marketCountryCode],
                    'residency_country_codes' => [$marketCountryCode],
                ], JSON_THROW_ON_ERROR),
                'released_at' => now(),
                'lifecycle_status' => 'active',
                'kill_switch_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
            DB::table('ai_model_versions')->insert([
                'id' => $modelVersionId,
                'model_id' => $modelId,
                'provider_version_id' => $providerVersionId,
                'version' => 1,
                'checksum' => hash('sha256', $modelVersionId),
                'context_window' => $maxOutputTokens,
                'input_cost_minor' => 0,
                'output_cost_minor' => 2_500,
                'currency' => 'USD',
                'configuration' => json_encode([
                    'capabilities' => ['structured_output'],
                    'allowed_market_country_codes' => [$marketCountryCode],
                    'residency_country_codes' => [$marketCountryCode],
                ], JSON_THROW_ON_ERROR),
                'released_at' => now(),
                'lifecycle_status' => 'active',
                'kill_switch_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $providerId = (string) $provider->id;
            $providerVersionId = (string) DB::table('ai_provider_versions')
                ->where('provider_id', $providerId)
                ->where('status', 'active')
                ->value('id');
            $modelId = (string) DB::table('ai_models')
                ->where('provider_id', $providerId)
                ->where('key', $modelKey)
                ->value('id');
            $modelVersionId = (string) DB::table('ai_model_versions')
                ->where('model_id', $modelId)
                ->where('provider_version_id', $providerVersionId)
                ->value('id');
        }

        $promptDefinitionId = (string) Str::uuid();
        $promptVersionId = (string) Str::uuid();
        $agentDefinitionId = (string) Str::uuid();
        $agentVersionId = (string) Str::uuid();
        DB::table('ai_prompt_definitions')->insert([
            'id' => $promptDefinitionId,
            'tenant_id' => $tenantId,
            'key' => 'fixture.prompt.'.Str::uuid(),
            'name' => 'Fixture prompt',
            'visibility' => $visibility,
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $encrypted = app(LaravelAiContentCipher::class)->encrypt(
            $tenantId,
            $promptVersionId,
            'prompt_template',
            $systemPrompt,
        );
        DB::table('ai_prompt_versions')->insert([
            'id' => $promptVersionId,
            'tenant_id' => $tenantId,
            'prompt_definition_id' => $promptDefinitionId,
            'version' => 1,
            'checksum' => hash('sha256', $promptVersionId),
            'template_ciphertext' => $encrypted->ciphertext,
            'template_hash' => $encrypted->contentHash,
            'encryption_key_version' => $encrypted->keyVersion,
            'input_schema' => json_encode([], JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'lifecycle_status' => 'active',
            'kill_switch_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_agent_definitions')->insert([
            'id' => $agentDefinitionId,
            'tenant_id' => $tenantId,
            'key' => 'fixture.agent.'.Str::uuid(),
            'name' => 'Fixture agent',
            'visibility' => $visibility,
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_agent_versions')->insert([
            'id' => $agentVersionId,
            'tenant_id' => $tenantId,
            'agent_definition_id' => $agentDefinitionId,
            'model_version_id' => $modelVersionId,
            'version' => 1,
            'checksum' => hash('sha256', $agentVersionId),
            'configuration' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'lifecycle_status' => 'active',
            'kill_switch_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new AiRuntimeSelection(
            providerVersionId: $providerVersionId,
            modelVersionId: $modelVersionId,
            agentVersionId: $agentVersionId,
            promptVersionId: $promptVersionId,
            providerKey: $providerKey,
            adapterKey: $adapterKey,
            secretReference: $providerKey.'.primary',
            modelKey: $modelKey,
            systemPrompt: $systemPrompt,
            outputSchema: $outputSchema,
            requiredCapabilities: ['structured_output'],
            maxOutputTokens: $maxOutputTokens,
            releaseDecisionId: (string) Str::uuid(),
        );
    }
}
