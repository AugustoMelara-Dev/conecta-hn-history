<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\TestCase;

final class ConversationStoreMySqlConcurrencyTest extends TestCase
{
    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();

        parent::tearDown();
    }

    public function test_row_lock_serializes_concurrent_sequence_claims_without_duplicates(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('This proof requires the certified disposable MySQL harness.');
        }

        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $initial = app(ConversationStore::class)->claim($this->command(
            (string) $tenant->id,
            'mysql-sequence-001',
        ));
        app(TenantContext::class)->reset();

        $first = $this->worker((string) $tenant->id, 'mysql-sequence-002', true);
        $second = $this->worker((string) $tenant->id, 'mysql-sequence-003', false);
        $first['process']->start();
        $this->awaitFile($first['ready'], $first['process']);
        file_put_contents($first['go'], 'go');
        $this->awaitFile($first['locked'], $first['process']);
        $second['process']->start();
        $this->awaitFile($second['ready'], $second['process']);
        $blockedSince = microtime(true);
        file_put_contents($second['go'], 'go');
        $this->awaitFile($second['attempting'], $second['process']);
        usleep(600_000);
        self::assertTrue($second['process']->isRunning());
        self::assertSame('', trim($second['process']->getOutput()));
        file_put_contents($first['release'], 'release');
        $first['process']->wait();
        $second['process']->wait();

        $firstResult = $this->workerResult($first['process']);
        $secondResult = $this->workerResult($second['process']);
        self::assertGreaterThanOrEqual(0.5, (float) $secondResult['completed_at'] - $blockedSince);
        self::assertSame($initial->conversationId, $firstResult['conversation_id']);
        self::assertSame($initial->conversationId, $secondResult['conversation_id']);
        self::assertSame([1, 2, 3], DB::table('ai_messages')
            ->where('conversation_id', $initial->conversationId)
            ->orderBy('sequence')
            ->pluck('sequence')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all());
        self::assertSame(3, DB::table('ai_messages')
            ->where('conversation_id', $initial->conversationId)
            ->distinct()
            ->count('sequence'));
        self::assertSame(3, (int) DB::table('ai_conversations')
            ->where('id', $initial->conversationId)
            ->value('last_message_sequence'));

        foreach ([$first, $second] as $worker) {
            foreach (['ready', 'go', 'attempting', 'locked', 'release'] as $path) {
                @unlink($worker[$path]);
            }
        }
    }

    public function test_concurrent_identical_and_changed_keys_reconcile_for_new_and_external_threads(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('This proof requires the certified disposable MySQL harness.');
        }

        $tenant = Tenant::factory()->create();
        $this->supportMarket();

        foreach ([
            ['new-identical', null, 'Same standalone request.', 'Same standalone request.', 'accepted'],
            ['new-changed', null, 'First standalone request.', 'Changed standalone request.', 'conflict'],
            ['external-identical', 'mysql-idempotency-thread-a', 'Same external request.', 'Same external request.', 'accepted'],
            ['external-changed', 'mysql-idempotency-thread-b', 'First external request.', 'Changed external request.', 'conflict'],
        ] as [$key, $thread, $firstContent, $secondContent, $secondStatus]) {
            $first = $this->worker(
                (string) $tenant->id,
                "mysql-race-{$key}",
                true,
                $thread,
                $firstContent,
            );
            $second = $this->worker(
                (string) $tenant->id,
                "mysql-race-{$key}",
                false,
                $thread,
                $secondContent,
            );

            try {
                $first['process']->start();
                $this->awaitFile($first['ready'], $first['process']);
                file_put_contents($first['go'], 'go');
                $this->awaitFile($first['locked'], $first['process']);
                $second['process']->start();
                $this->awaitFile($second['ready'], $second['process']);
                file_put_contents($second['go'], 'go');
                $this->awaitFile($second['attempting'], $second['process']);
                usleep(300_000);
                self::assertTrue($second['process']->isRunning());
                file_put_contents($first['release'], 'release');
                $first['process']->wait();
                $second['process']->wait();

                $firstResult = $this->workerResult($first['process']);
                $secondResult = $this->workerResult($second['process']);
                self::assertSame('accepted', $firstResult['status']);
                self::assertSame($secondStatus, $secondResult['status']);
                if ($secondStatus === 'accepted') {
                    self::assertSame($firstResult['conversation_id'], $secondResult['conversation_id']);
                    self::assertSame($firstResult['message_id'], $secondResult['message_id']);
                    self::assertFalse($firstResult['replayed']);
                    self::assertTrue($secondResult['replayed']);
                } else {
                    self::assertSame('ai_request_conflict', $secondResult['public_code']);
                }
                self::assertSame(1, DB::table('ai_messages')
                    ->where('tenant_id', $tenant->id)
                    ->where('idempotency_key', "mysql-race-{$key}")
                    ->count());
            } finally {
                $this->cleanupWorker($first);
                $this->cleanupWorker($second);
            }
        }
    }

    public function test_completion_locks_execution_lineage_and_evidence_until_atomic_commit(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('This proof requires the certified disposable MySQL harness.');
        }

        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        app(TenantContext::class)->setTenant((string) $tenant->id);

        foreach (['execution', 'evidence'] as $holdOn) {
            $claim = app(ConversationStore::class)->claim($this->command(
                (string) $tenant->id,
                "mysql-completion-lock-{$holdOn}",
            ));
            $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
            $result = $this->executionResult(
                (string) $tenant->id,
                $claim->conversationId,
                $claim->requestMessage->id,
                $selection,
            );
            $product = $holdOn === 'evidence'
                ? Product::factory()->forTenant($tenant)->create()
                : null;
            $evidence = $product === null ? [] : [[
                'evidence_type' => 'resource',
                'resource_type' => 'product',
                'resource_id' => (string) $product->id,
                'source_tenant_id' => (string) $tenant->id,
            ]];
            app(TenantContext::class)->reset();

            $completion = $this->completionWorker([
                'mode' => 'complete',
                'hold_on' => $holdOn,
                'tenant_id' => (string) $tenant->id,
                'conversation_id' => $claim->conversationId,
                'request_message_id' => $claim->requestMessage->id,
                'selection' => $this->selectionPayload($selection),
                'result' => $this->resultPayload($result),
                'evidence' => $evidence,
            ]);
            $mutation = $this->completionWorker([
                'mode' => $holdOn === 'execution' ? 'mutate_execution' : 'delete_evidence',
                'tenant_id' => (string) $tenant->id,
                'correlation_id' => $result->correlationId,
                'resource_id' => $product === null ? null : (string) $product->id,
            ]);

            try {
                $completion['process']->start();
                $this->awaitFile($completion['ready'], $completion['process']);
                file_put_contents($completion['go'], 'go');
                $this->awaitFile($completion['locked'], $completion['process']);
                $mutation['process']->start();
                $this->awaitFile($mutation['ready'], $mutation['process']);
                file_put_contents($mutation['go'], 'go');
                $this->awaitFile($mutation['attempting'], $mutation['process']);
                $blockedSince = microtime(true);
                usleep(600_000);
                self::assertTrue($mutation['process']->isRunning());
                self::assertSame('', trim($mutation['process']->getOutput()));
                file_put_contents($completion['release'], 'release');
                $completion['process']->wait();
                $mutation['process']->wait();

                $completionResult = $this->workerResult($completion['process']);
                $mutationResult = $this->workerResult($mutation['process']);
                self::assertSame('completed', $completionResult['status']);
                self::assertSame('completed', $mutationResult['status']);
                self::assertGreaterThanOrEqual(
                    0.5,
                    (float) $mutationResult['completed_at'] - $blockedSince,
                );
                self::assertSame(1, DB::table('ai_messages')
                    ->where('id', $completionResult['message_id'])
                    ->where('role', 'assistant')
                    ->count());
                if ($holdOn === 'evidence') {
                    self::assertSame(1, DB::table('ai_message_evidence')
                        ->where('message_id', $completionResult['message_id'])
                        ->where('resource_id', $product->id)
                        ->count());
                }
            } finally {
                $this->cleanupWorker($completion);
                $this->cleanupWorker($mutation);
            }

            app(TenantContext::class)->setTenant((string) $tenant->id);
        }
    }

    private function command(string $tenantId, string $idempotencyKey): AcceptConversationMessage
    {
        return new AcceptConversationMessage(
            tenantId: $tenantId,
            visibility: 'tenant_private',
            originType: 'web',
            externalThreadReference: 'mysql-sequence-thread',
            actorId: 'owner-001',
            locale: 'en-CA',
            marketCountryCode: 'CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            content: 'Concurrent claim '.$idempotencyKey,
            contentType: 'text',
            idempotencyKey: $idempotencyKey,
        );
    }

    private function supportMarket(): void
    {
        $now = now();
        DB::table('countries')->insertOrIgnore([
            'code' => 'CA', 'name' => 'Canada', 'native_name' => 'Canada',
            'calling_code' => '+1', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('currencies')->insertOrIgnore([
            'code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$',
            'minor_unit' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('locales')->insertOrIgnore([
            'code' => 'en-CA', 'name' => 'English (Canada)', 'native_name' => 'English (Canada)',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('timezones')->insertOrIgnore([
            'name' => 'America/Toronto', 'label' => 'Toronto', 'utc_offset' => '-05:00',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** @return array{process:Process,ready:string,go:string,attempting:string,locked:string,release:string} */
    private function worker(
        string $tenantId,
        string $idempotencyKey,
        bool $hold,
        ?string $externalThreadReference = 'mysql-sequence-thread',
        ?string $content = null,
    ): array {
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dovra-conversation-race-'.Str::uuid();
        $paths = [
            'ready' => $prefix.'.ready',
            'go' => $prefix.'.go',
            'attempting' => $prefix.'.attempting',
            'locked' => $prefix.'.locked',
            'release' => $prefix.'.release',
        ];
        $payload = base64_encode(json_encode([
            'tenant_id' => $tenantId,
            'idempotency_key' => $idempotencyKey,
            'external_thread_reference' => $externalThreadReference,
            'content' => $content ?? 'Concurrent claim '.$idempotencyKey,
            'hold' => $hold,
            ...array_combine(
                array_map(static fn (string $key): string => "{$key}_path", array_keys($paths)),
                array_values($paths),
            ),
        ], JSON_THROW_ON_ERROR));

        return [
            'process' => new Process([
                PHP_BINARY,
                base_path('tests/Support/AI/run_conversation_claim_worker.php'),
                $payload,
            ], base_path(), null, null, 60),
            ...$paths,
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array{process:Process,ready:string,go:string,attempting:string,locked:string,release:string}
     */
    private function completionWorker(array $payload): array
    {
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dovra-completion-race-'.Str::uuid();
        $paths = [
            'ready' => $prefix.'.ready',
            'go' => $prefix.'.go',
            'attempting' => $prefix.'.attempting',
            'locked' => $prefix.'.locked',
            'release' => $prefix.'.release',
        ];
        $encoded = base64_encode(json_encode([
            ...$payload,
            ...array_combine(
                array_map(static fn (string $key): string => "{$key}_path", array_keys($paths)),
                array_values($paths),
            ),
        ], JSON_THROW_ON_ERROR));

        return [
            'process' => new Process([
                PHP_BINARY,
                base_path('tests/Support/AI/run_conversation_completion_worker.php'),
                $encoded,
            ], base_path(), null, null, 60),
            ...$paths,
        ];
    }

    /** @param array{process:Process,ready:string,go:string,attempting:string,locked:string,release:string} $worker */
    private function cleanupWorker(array $worker): void
    {
        if ($worker['process']->isRunning()) {
            $worker['process']->stop(1);
        }
        foreach (['ready', 'go', 'attempting', 'locked', 'release'] as $path) {
            @unlink($worker[$path]);
        }
    }

    /** @return array<string, mixed> */
    private function selectionPayload(AiRuntimeSelection $selection): array
    {
        return [
            'providerVersionId' => $selection->providerVersionId,
            'modelVersionId' => $selection->modelVersionId,
            'agentVersionId' => $selection->agentVersionId,
            'promptVersionId' => $selection->promptVersionId,
            'providerKey' => $selection->providerKey,
            'adapterKey' => $selection->adapterKey,
            'secretReference' => $selection->secretReference,
            'modelKey' => $selection->modelKey,
            'systemPrompt' => $selection->systemPrompt,
            'outputSchema' => $selection->outputSchema,
            'requiredCapabilities' => $selection->requiredCapabilities,
            'maxOutputTokens' => $selection->maxOutputTokens,
            'releaseDecisionId' => $selection->releaseDecisionId,
        ];
    }

    /** @return array<string, mixed> */
    private function resultPayload(AiExecutionResult $result): array
    {
        return [
            'correlationId' => $result->correlationId,
            'provider' => $result->provider,
            'model' => $result->model,
            'data' => $result->data,
            'usage' => $result->usage,
            'latencyMilliseconds' => $result->latencyMilliseconds,
        ];
    }

    private function executionResult(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        AiRuntimeSelection $selection,
    ): AiExecutionResult {
        $correlationId = (string) Str::uuid();
        DB::table('ai_executions')->insert([
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'billing_tenant_id' => $tenantId,
            'actor_id' => 'owner-001',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'model' => $selection->modelKey,
            'request_hash' => hash('sha256', 'mysql-completion-request'),
            'claim_token' => hash('sha256', 'mysql-completion-claim'),
            'claim_version' => 1,
            'status' => 'completed',
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDay(),
            'processing_expires_at' => now()->addMinute(),
            'provider_completed_at' => now(),
            'completed_at' => now(),
            'conversation_id' => $conversationId,
            'request_message_id' => $requestMessageId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new AiExecutionResult(
            correlationId: $correlationId,
            provider: $selection->providerKey,
            model: $selection->modelKey,
            data: ['answer' => 'Atomic completion.'],
            usage: [
                'input_tokens' => 10,
                'output_tokens' => 8,
                'cost_usd' => 0.01,
                'charged_cost_usd' => 0.01,
                'provider_cost_usd' => 0.01,
            ],
            latencyMilliseconds: 42,
        );
    }

    private function awaitFile(string $path, Process $process): void
    {
        $deadline = microtime(true) + 30;
        while (! is_file($path)) {
            if (! $process->isRunning()) {
                self::fail(trim($process->getErrorOutput()."\n".$process->getOutput()));
            }
            if (microtime(true) >= $deadline) {
                self::fail("Worker did not signal: {$path}");
            }
            usleep(10_000);
        }
    }

    /** @return array<string, mixed> */
    private function workerResult(Process $process): array
    {
        self::assertTrue(
            $process->isSuccessful(),
            trim($process->getErrorOutput()."\n".$process->getOutput()),
        );
        $result = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);

        return $result;
    }
}
