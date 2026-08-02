<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class DovraWorkforceMigrationContractTest extends TestCase
{
    public function test_task8_expansions_are_tenant_scoped_and_rls_ready(): void
    {
        $contracts = [
            [
                'path' => 'database/migrations/2026_08_02_210000_expand_dovra_business_graph.php',
                'markers' => ['dovra_business_contexts', 'dovra_business_facts', 'dovra_business_edges', 'foreignUuid(\'tenant_id\')'],
            ],
            [
                'path' => 'database/migrations/2026_08_02_210100_expand_dovra_agent_outcomes.php',
                'markers' => ['dovra_agent_action_audits', 'dovra_agent_outcomes', 'idempotency_key', 'payload_hash', 'ENABLE ROW LEVEL SECURITY', 'FORCE ROW LEVEL SECURITY'],
            ],
            [
                'path' => 'database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php',
                'markers' => ['dovra_conversation_channel_links', 'lineage_idempotency_key', 'external_thread_reference', 'current_setting(\'app.tenant_id\'', 'WITH CHECK'],
            ],
            [
                'path' => 'database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php',
                'markers' => ['dovra_workforce_activation_requests', 'idempotency_key', 'request_hash', 'candidate_agent_version_id', 'release_decision_id', 'restrictOnDelete', 'current_setting(\'app.tenant_id\'', 'WITH CHECK'],
            ],
        ];

        foreach ($contracts as $contract) {
            $contents = $this->read($contract['path']);
            foreach ($contract['markers'] as $marker) {
                self::assertStringContainsString($marker, $contents, $contract['path'].' is missing '.$marker);
            }
        }
    }

    public function test_activation_request_contract_is_idempotent_draft_only_and_never_live_by_schema(): void
    {
        $migration = $this->read('database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php');
        $controller = $this->read('app/Http/Controllers/Api/V1/TenantWorkforceController.php');

        self::assertStringContainsString("\$table->unique(['tenant_id', 'idempotency_key']", $migration);
        self::assertStringContainsString('dovra_workforce_activation_candidate_fk', $migration);
        self::assertStringContainsString('dovra_workforce_activation_release_fk', $migration);
        self::assertStringNotContainsString('nullOnDelete()', $migration);
        self::assertStringContainsString("'candidate_status' => \$candidate?->lifecycle_status ?? 'not_created'", $controller);
        self::assertStringContainsString("'state' => $state", $controller);
        self::assertStringContainsString("'simulation'", $controller);
        self::assertStringNotContainsString("DB::table('ai_agent_activations')->insert", $controller);
    }

    public function test_composite_foreign_keys_and_rls_policy_keep_tenant_and_related_ids_aligned(): void
    {
        $activation = $this->read('database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php');
        $channel = $this->read('database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php');
        $outcomes = $this->read('database/migrations/2026_08_02_210100_expand_dovra_agent_outcomes.php');

        self::assertStringContainsString("foreign(['tenant_id', 'candidate_agent_version_id']", $activation);
        self::assertStringContainsString("references(['tenant_id', 'id'])->on('ai_agent_versions')", $activation);
        self::assertStringContainsString("foreign(['tenant_id', 'release_decision_id']", $activation);
        self::assertStringContainsString("foreign(['tenant_id', 'conversation_id']", $channel);
        self::assertStringContainsString("references(['tenant_id', 'id'])->on('ai_conversations')", $channel);
        self::assertStringContainsString("tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid", $channel);
        self::assertStringContainsString("tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid", $activation);
        self::assertStringContainsString('tenant_isolation', $outcomes);
    }

    public function test_expansion_up_methods_do_not_contract_or_delete_legacy_data(): void
    {
        foreach ([
            'database/migrations/2026_08_02_210000_expand_dovra_business_graph.php',
            'database/migrations/2026_08_02_210100_expand_dovra_agent_outcomes.php',
            'database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php',
            'database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php',
        ] as $path) {
            $contents = $this->read($path);
            $up = explode('public function down', $contents, 2)[0];

            self::assertStringNotContainsString('dropIfExists', $up, $path.' must be expand-only in up().');
            self::assertStringNotContainsString('Schema::drop', $up, $path.' must not contract in up().');
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        self::assertIsString($contents, 'Expected migration or controller to be readable: '.$relativePath);

        return $contents;
    }
}
