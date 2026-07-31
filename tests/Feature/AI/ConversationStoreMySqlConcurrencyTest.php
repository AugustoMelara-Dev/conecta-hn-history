<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
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
        DB::table('countries')->insert([
            'code' => 'CA', 'name' => 'Canada', 'native_name' => 'Canada',
            'calling_code' => '+1', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('currencies')->insert([
            'code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$',
            'minor_unit' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('locales')->insert([
            'code' => 'en-CA', 'name' => 'English (Canada)', 'native_name' => 'English (Canada)',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('timezones')->insert([
            'name' => 'America/Toronto', 'label' => 'Toronto', 'utc_offset' => '-05:00',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** @return array{process:Process,ready:string,go:string,attempting:string,locked:string,release:string} */
    private function worker(string $tenantId, string $idempotencyKey, bool $hold): array
    {
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
            'external_thread_reference' => 'mysql-sequence-thread',
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
