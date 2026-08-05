<?php

declare(strict_types=1);

namespace Tests\Support\AI;

use App\Application\Ai\Contracts\AiProviderPool;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fakes\AI\FakeAiProvider;

/**
 * Test-only fixture for the Program 05 composite certificate.
 *
 * The fixture deliberately owns only synthetic rows in the disposable test
 * database. Registry activation/rollback and sticky-path writes remain in the
 * production authorities exercised by the dedicated feature test.
 */
final class Program05CompositeCertificateFixture
{
    /** @return array<string, mixed> */
    public static function create(): array
    {
        self::assertGuard();

        // Bind exactly one deterministic fake before either registry fixture
        // is created or evaluated. The composite must prove that every stage
        // remains provider-call free without allowing a later fixture to
        // replace this binding.
        $provider = new FakeAiProvider('fake');
        app()->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            'fake_structured_text' => [
                'fake.primary' => $provider,
            ],
        ]));

        $tenant = Tenant::factory()->create(['name' => 'Program 05 Composite Tenant']);
        $tenantId = (string) $tenant->getKey();
        $actor = User::factory()->superAdmin()->create([
            'name' => 'Program 05 Composite Actor',
            'email' => 'program05-composite-actor@example.test',
        ]);
        config(['auth.super_admins' => [$actor->email]]);

        $foreignTenant = Tenant::factory()->create(['name' => 'Program 05 Foreign Sentinel']);
        self::seedForeignSentinel((string) $foreignTenant->getKey());

        $root = AiEligibleRegistryFixture::create(
            tenantId: $tenantId,
            capability: 'private_tenant_agent',
            audience: 'private',
            outputSchema: 'private_agent.answer.v1',
            maxOutputTokens: 800,
            marketCountryCode: 'HN',
        );
        $candidate = AiEligibleRegistryFixture::create(
            tenantId: $tenantId,
            capability: 'private_tenant_agent',
            audience: 'private',
            outputSchema: 'private_agent.answer.v1',
            maxOutputTokens: 800,
            marketCountryCode: 'HN',
        );

        $rootActivation = DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $root->agentVersionId)
            ->whereNull('effective_until')
            ->first();
        $candidateActivation = DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $candidate->agentVersionId)
            ->whereNull('effective_until')
            ->first();
        if ($rootActivation === null || $candidateActivation === null) {
            throw new \RuntimeException('Program 05 fixture could not locate root/candidate activations.');
        }

        // Close only the candidate fixture activation for this tenant and
        // return only that candidate version to eligible before lifecycle use.
        DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('id', $candidateActivation->id)
            ->where('agent_version_id', $candidate->agentVersionId)
            ->whereNull('effective_until')
            ->update([
                'effective_until' => now()->subSecond(),
                'updated_at' => now()->subSecond(),
            ]);
        DB::table('ai_agent_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $candidate->agentVersionId)
            ->where('lifecycle_status', 'active')
            ->update([
                'lifecycle_status' => 'eligible',
                'updated_at' => now()->subSecond(),
            ]);

        $conversations = [];
        $executions = [];
        $labels = [
            'legacy-01',
            'legacy-02',
            'legacy-03',
            'legacy-04',
            'legacy-05',
            'canary',
            'control',
        ];
        foreach ($labels as $index => $label) {
            $conversationId = self::conversation($tenantId, (string) $actor->getKey(), $index);
            [$requestId, $responseId] = self::messages(
                tenantId: $tenantId,
                conversationId: $conversationId,
                selection: $root,
                index: $index,
            );
            $executionId = self::execution(
                tenantId: $tenantId,
                requestId: $requestId,
                responseId: $responseId,
                selection: $root,
                index: $index,
            );
            DB::table('ai_messages')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', [$requestId, $responseId])
                ->update(['execution_id' => $executionId, 'updated_at' => now()->subMinutes(100 - $index)]);
            $conversations[$label] = $conversationId;
            $executions[$label] = $executionId;
        }

        return [
            'tenant_id' => $tenantId,
            'actor_id' => (string) $actor->getKey(),
            'foreign_tenant_id' => (string) $foreignTenant->getKey(),
            'root' => [
                'provider_version_id' => $root->providerVersionId,
                'model_version_id' => $root->modelVersionId,
                'agent_version_id' => $root->agentVersionId,
                'prompt_version_id' => $root->promptVersionId,
                'release_decision_id' => $root->releaseDecisionId,
                'activation_id' => (string) $rootActivation->id,
            ],
            'candidate' => [
                'provider_version_id' => $candidate->providerVersionId,
                'model_version_id' => $candidate->modelVersionId,
                'agent_version_id' => $candidate->agentVersionId,
                'prompt_version_id' => $candidate->promptVersionId,
                'release_decision_id' => $candidate->releaseDecisionId,
            ],
            'conversations' => $conversations,
            'executions' => $executions,
            'provider' => $provider,
            'expected_legacy_count' => count($executions),
            // The foreign sentinel intentionally exercises the same
            // conversation-retention surface. The production backfill scans
            // that table globally, so the normalized expectation includes
            // the pre-classified sentinel row while its semantic hash must
            // remain unchanged.
            'expected_conversation_count' => count($conversations) + 1,
        ];
    }

    /** @return array{canary_subject:string,control_subject:string} */
    public static function cohortSubjects(string $salt, int $rolloutPercentage): array
    {
        if ($rolloutPercentage < 1 || $rolloutPercentage > 99) {
            throw new \InvalidArgumentException('Program 05 cohort rollout percentage must be between 1 and 99.');
        }

        $canary = null;
        $control = null;
        for ($index = 0; $index < 100_000 && ($canary === null || $control === null); $index++) {
            $subject = 'program05-composite-subject-'.$index;
            $bucket = hexdec(substr(hash('sha256', $salt.'|'.$subject), 0, 8)) % 100;
            if ($canary === null && $bucket < $rolloutPercentage) {
                $canary = $subject;
            }
            if ($control === null && $bucket >= $rolloutPercentage) {
                $control = $subject;
            }
        }
        if ($canary === null || $control === null) {
            throw new \RuntimeException('Program 05 fixture could not derive both cohort subjects.');
        }

        return [
            'canary_subject' => $canary,
            'control_subject' => $control,
        ];
    }

    /** @return array<string, string|int|null> */
    public static function canonicalState(string $tenantId): array
    {
        $tables = [
            'ai_agent_definitions',
            'ai_agent_versions',
            'ai_prompt_definitions',
            'ai_prompt_versions',
            'ai_release_policies',
            'ai_release_decisions',
            'ai_agent_activations',
            'ai_conversations',
            'ai_conversation_participants',
            'ai_messages',
            'ai_executions',
        ];

        $rows = [];
        $counts = [];
        foreach ($tables as $table) {
            $query = DB::table($table);
            if ($table === 'ai_agent_definitions' || $table === 'ai_agent_versions'
                || $table === 'ai_prompt_definitions' || $table === 'ai_prompt_versions'
                || $table === 'ai_release_policies' || $table === 'ai_release_decisions'
                || $table === 'ai_agent_activations' || $table === 'ai_conversations'
                || $table === 'ai_conversation_participants' || $table === 'ai_messages') {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->where('tenant_id', $tenantId);
            }
            $tableRows = $query->get()->map(static function (object $row): array {
                $values = (array) $row;
                foreach (['id', 'correlation_id', 'tenant_id', 'conversation_id', 'request_message_id', 'response_message_id', 'agent_version_id', 'prompt_version_id', 'provider_version_id', 'model_version_id', 'execution_id', 'release_decision_id', 'previous_activation_id', 'activated_by'] as $key) {
                    unset($values[$key]);
                }
                foreach (['created_at', 'updated_at', 'effective_from', 'effective_until', 'retention_expires_at', 'accepted_at', 'completed_at', 'provider_completed_at', 'processing_expires_at', 'failed_at', 'payload_pruned_at'] as $key) {
                    unset($values[$key]);
                }
                ksort($values);

                return $values;
            })->all();
            $rows[$table] = $tableRows;
            $counts[$table] = count($tableRows);
        }
        ksort($rows);
        ksort($counts);

        return [
            'row_count' => array_sum($counts),
            'table_count' => count($tables),
            'state_sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'counts_sha256' => hash('sha256', json_encode($counts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }

    /** @return array<string, string|int|null> */
    public static function legacyState(string $tenantId): array
    {
        $rows = DB::table('ai_executions')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->orderBy('correlation_id')
            ->get()
            ->map(static function (object $row): array {
                return [
                    'provider' => (string) $row->provider,
                    'model' => (string) $row->model,
                    'status' => (string) $row->status,
                    'audience' => (string) $row->audience,
                    'capability' => (string) $row->capability,
                    'operation' => (string) $row->operation,
                    'result_hash' => (string) $row->result_hash,
                    'request_hash' => (string) $row->request_hash,
                    'claim_version' => (int) $row->claim_version,
                ];
            })->all();

        return [
            'execution_count' => count($rows),
            'state_sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }

    private static function assertGuard(): void
    {
        if (app()->environment() !== 'testing') {
            throw new \RuntimeException('Program 05 composite fixture requires testing environment.');
        }
        if ((string) config('database.default') !== 'sqlite' || (string) getenv('DB_CONNECTION') !== 'sqlite') {
            throw new \RuntimeException('Program 05 composite fixture requires SQLite.');
        }
        if (strtolower((string) getenv('DOVRA_PROGRAM05_COMPOSITE_ALLOW')) !== 'true') {
            throw new \RuntimeException('Program 05 composite fixture guard is disabled.');
        }
        $database = (string) getenv('DOVRA_PROGRAM05_COMPOSITE_DATABASE');
        $environmentDatabase = (string) getenv('DB_DATABASE');
        $configuredDatabase = (string) config('database.connections.sqlite.database');
        if (! self::isAbsolute($database)
            || ! is_file($database)
            || $environmentDatabase !== $database
            || $configuredDatabase !== $database) {
            throw new \RuntimeException('Program 05 composite fixture requires one exact disposable SQLite database path across DOVRA_PROGRAM05_COMPOSITE_DATABASE, DB_DATABASE, and resolved sqlite configuration.');
        }
        self::assertNoDatabaseAlias($database);
    }

    private static function assertNoDatabaseAlias(string $database): void
    {
        $cursor = $database;
        while (true) {
            if (is_link($cursor)) {
                throw new \RuntimeException('Program 05 composite fixture rejects symlink database aliases.');
            }
            $real = realpath($cursor);
            if ($real !== false && ! self::pathsEqual($real, $cursor)) {
                throw new \RuntimeException('Program 05 composite fixture rejects reparse-point or canonical-path database aliases.');
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                break;
            }
            $cursor = $parent;
        }
    }

    private static function pathsEqual(string $left, string $right): bool
    {
        $left = str_replace('/', DIRECTORY_SEPARATOR, $left);
        $right = str_replace('/', DIRECTORY_SEPARATOR, $right);

        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($left, $right) === 0
            : $left === $right;
    }

    private static function seedForeignSentinel(string $tenantId): void
    {
        $definitionId = (string) Str::uuid();
        DB::table('ai_agent_definitions')->insert([
            'id' => $definitionId,
            'tenant_id' => $tenantId,
            'key' => 'program05.foreign.sentinel',
            'name' => 'Foreign sentinel without route',
            'visibility' => 'private',
            'status' => 'active',
            'metadata' => json_encode(['sentinel' => true], JSON_THROW_ON_ERROR),
            'created_at' => now()->subMinutes(500),
            'updated_at' => now()->subMinutes(500),
        ]);

        // Keep a pre-classified conversation/participant surface in the
        // foreign tenant. Backfill is allowed to classify empty retention
        // fields and insert an owner participant, so this sentinel proves
        // those writes remain tenant-preserving while still exercising the
        // same tables as the subject tenant. No eligible registry route is
        // created for this tenant.
        $conversationId = (string) Str::uuid();
        $at = now()->subMinutes(450);
        DB::table('ai_conversations')->insert([
            'id' => $conversationId,
            'tenant_id' => $tenantId,
            'visibility' => 'tenant_private',
            'origin_type' => 'web',
            'external_thread_hash' => null,
            'actor_id' => 'foreign-sentinel-actor',
            'agent_version_id' => null,
            'locale' => 'es-HN',
            'market_country_code' => 'HN',
            'currency' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'status' => 'active',
            'retention_category' => 'conversation_standard',
            'retention_expires_at' => $at->copy()->addDays(30),
            'last_message_sequence' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        DB::table('ai_conversation_participants')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'participant_type' => 'actor',
            'participant_id' => 'foreign-sentinel-actor',
            'role' => 'owner',
            'joined_at' => $at,
            'left_at' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private static function conversation(string $tenantId, string $actorId, int $index): string
    {
        $id = (string) Str::uuid();
        $at = now()->subMinutes(200 - $index);
        DB::table('ai_conversations')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'visibility' => 'tenant_private',
            'origin_type' => 'web',
            'external_thread_hash' => null,
            'actor_id' => $actorId,
            'agent_version_id' => null,
            'locale' => 'es-HN',
            'market_country_code' => 'HN',
            'currency' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'status' => 'active',
            'retention_category' => '',
            'retention_expires_at' => null,
            'last_message_sequence' => 2,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        DB::table('ai_conversation_participants')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'conversation_id' => $id,
            'participant_type' => 'actor',
            'participant_id' => $actorId,
            'role' => 'owner',
            'joined_at' => $at,
            'left_at' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return $id;
    }

    /** @return array{0:string,1:string} */
    private static function messages(string $tenantId, string $conversationId, object $selection, int $index): array
    {
        $requestId = (string) Str::uuid();
        $responseId = (string) Str::uuid();
        $at = now()->subMinutes(200 - $index);
        DB::table('ai_messages')->insert([
            [
                'id' => $requestId,
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'sequence' => 1,
                'direction' => 'inbound',
                'role' => 'user',
                'content_ciphertext' => 'legacy-request-ciphertext',
                'content_hash' => hash('sha256', $requestId),
                'encryption_key_version' => 'fixture-v1',
                'content_type' => 'text',
                'idempotency_key' => 'program05-request-'.$requestId,
                'idempotency_hash' => hash('sha256', 'program05-request-'.$requestId),
                'status' => 'accepted',
                'agent_version_id' => null,
                'prompt_version_id' => null,
                'provider_version_id' => null,
                'model_version_id' => null,
                'execution_id' => null,
                'accepted_at' => $at,
                'completed_at' => null,
                'failed_at' => null,
                'created_at' => $at,
                'updated_at' => $at,
            ],
            [
                'id' => $responseId,
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'sequence' => 2,
                'direction' => 'outbound',
                'role' => 'assistant',
                'content_ciphertext' => 'legacy-response-ciphertext',
                'content_hash' => hash('sha256', $responseId),
                'encryption_key_version' => 'fixture-v1',
                'content_type' => 'application/json',
                'idempotency_key' => 'program05-response-'.$responseId,
                'idempotency_hash' => hash('sha256', 'program05-response-'.$responseId),
                'status' => 'completed',
                'agent_version_id' => $selection->agentVersionId,
                'prompt_version_id' => $selection->promptVersionId,
                'provider_version_id' => $selection->providerVersionId,
                'model_version_id' => $selection->modelVersionId,
                'execution_id' => null,
                'accepted_at' => $at,
                'completed_at' => $at,
                'failed_at' => null,
                'created_at' => $at,
                'updated_at' => $at,
            ],
        ]);

        return [$requestId, $responseId];
    }

    private static function execution(
        string $tenantId,
        string $requestId,
        string $responseId,
        object $selection,
        int $index,
    ): string {
        $id = (string) Str::uuid();
        $at = now()->subMinutes(190 - $index);
        DB::table('ai_executions')->insert([
            'correlation_id' => $id,
            'tenant_id' => $tenantId,
            'billing_tenant_id' => $tenantId,
            'actor_id' => 'program05-composite-actor',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'request_hash' => hash('sha256', 'program05-request-'.$id),
            'claim_token' => hash('sha256', 'program05-claim-'.$id),
            'claim_version' => 1,
            'status' => 'completed',
            'reservation_id' => null,
            'result_ciphertext' => 'legacy-result-ciphertext',
            'result_hash' => hash('sha256', 'program05-result-'.$id),
            'model' => $selection->modelKey,
            'usage' => json_encode(['input_tokens' => 1, 'output_tokens' => 1], JSON_THROW_ON_ERROR),
            'latency_milliseconds' => 1,
            'provider_cost' => 0,
            'charged_cost' => 0,
            'failure_code' => null,
            'encryption_key_version' => 'fixture-v1',
            'retention_expires_at' => now()->addDays(30),
            'processing_expires_at' => $at->subMinute(),
            'provider_completed_at' => $at,
            'completed_at' => $at,
            'failed_at' => null,
            'payload_pruned_at' => null,
            'conversation_id' => null,
            'request_message_id' => $requestId,
            'response_message_id' => $responseId,
            'provider_version_id' => null,
            'model_version_id' => null,
            'agent_version_id' => null,
            'prompt_version_id' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return $id;
    }

    private static function isAbsolute(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1;
    }
}
