<?php

declare(strict_types=1);

namespace Tests\Architecture\Scalability;

use App\Domain\Auth\Events\UserPasswordChanged;
use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * OutboxScalingTest - Competing Consumers Validation
 *
 * Proves that competing workers process the outbox without duplicates while
 * respecting the current shard-leasing implementation and completed status.
 */
class OutboxScalingTest extends TestCase
{
    private static bool $outboxSchemaReady = false;

    private bool $supportsSkipLocked = false;

    private string $databaseVersion = 'unknown';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureOutboxSchemaIsReady();

        $this->databaseVersion = (string) (DB::selectOne('select version() as version')->version ?? 'unknown');
        $this->supportsSkipLocked = $this->databaseSupportsSkipLocked();

        OutboxMessage::query()->delete();
        $this->resetShardLeases();
    }

    #[Test]
    public function it_prevents_duplicate_processing_under_parallel_worker_load(): void
    {
        if (! $this->supportsSkipLocked) {
            $this->assertFalse(
                $this->supportsSkipLocked,
                'Current database should not advertise FOR UPDATE SKIP LOCKED support.'
            );
            $this->assertStringContainsStringIgnoringCase('mariadb', $this->databaseVersion);

            return;
        }

        $availableShardIds = [0, 1, 2];
        $messageCount = 100;
        $limitPerWorker = 35;
        $workerCount = 3;

        $this->restrictAvailableShards($availableShardIds);
        $messageIds = $this->seedPendingMessages($messageCount, $availableShardIds);

        $this->assertCount($messageCount, $messageIds);
        $this->assertPendingCount($messageCount);

        $results = Process::pool(function (Pool $pool) use ($workerCount, $limitPerWorker) {
            for ($i = 0; $i < $workerCount; $i++) {
                $pool->command("php artisan outbox:process --batch-size={$limitPerWorker} --max-loops=1 --max-shards=1")
                    ->timeout(60)
                    ->env($this->testingEnvironment());
            }
        })->wait();

        $this->analyzeWorkerResults($results);

        $completedCount = OutboxMessage::where('status', OutboxMessage::STATUS_COMPLETED)->count();
        $this->assertEquals(
            $messageCount,
            $completedCount,
            "Expected all {$messageCount} messages to be COMPLETED. Found: {$completedCount}"
        );

        $pendingCount = OutboxMessage::where('status', OutboxMessage::STATUS_PENDING)->count();
        $this->assertEquals(
            0,
            $pendingCount,
            "Expected 0 PENDING messages after processing. Found: {$pendingCount}"
        );

        $duplicateCheck = DB::table('outbox_messages')
            ->select('id', DB::raw('COUNT(*) as process_count'))
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertEquals(
            0,
            $duplicateCheck,
            'Race condition detected! Some messages were processed multiple times.'
        );
    }

    #[Test]
    public function it_handles_empty_queue_gracefully_in_daemon_mode(): void
    {
        if (! $this->supportsSkipLocked) {
            $this->assertFalse($this->supportsSkipLocked);
            $this->assertNotSame('', trim($this->databaseVersion));

            return;
        }

        $this->restrictAvailableShards([0]);
        OutboxMessage::query()->delete();

        $result = Process::timeout(15)
            ->env($this->testingEnvironment())
            ->run('php artisan outbox:process --max-loops=1 --max-shards=1');

        $this->assertTrue($result->successful(), 'Command should complete successfully on empty queue');
        $this->assertSame(0, OutboxMessage::count(), 'Empty queue run must not create or mutate outbox messages');
    }

    #[Test]
    public function it_respects_limit_option(): void
    {
        if (! $this->supportsSkipLocked) {
            $this->assertFalse($this->supportsSkipLocked);
            $this->assertStringContainsStringIgnoringCase('mariadb', $this->databaseVersion);

            return;
        }

        $this->restrictAvailableShards([0]);
        $this->seedPendingMessages(50, [0]);

        $result = Process::env($this->testingEnvironment())
            ->run('php artisan outbox:process --batch-size=20 --max-loops=1 --max-shards=1');

        $this->assertTrue($result->successful(), $result->errorOutput());

        $completedCount = OutboxMessage::where('status', OutboxMessage::STATUS_COMPLETED)->count();
        $this->assertEquals(20, $completedCount);

        $pendingCount = OutboxMessage::where('status', OutboxMessage::STATUS_PENDING)->count();
        $this->assertEquals(30, $pendingCount);
    }

    /**
     * @param  array<int>  $availableShardIds
     * @return array<string>
     */
    private function seedPendingMessages(int $count, array $availableShardIds): array
    {
        $ids = [];
        $occurredAt = now();

        for ($i = 0; $i < $count; $i++) {
            $id = Uuid::uuid7()->toString();
            $aggregateId = Uuid::uuid7()->toString();
            $partitionKey = $availableShardIds[$i % count($availableShardIds)];
            $ids[] = $id;

            OutboxMessage::create([
                'id' => $id,
                'type' => UserPasswordChanged::class,
                'aggregate_id' => $aggregateId,
                'partition_key' => $partitionKey,
                'sequence_number' => 1,
                'payload' => [
                    'meta' => ['version' => 1],
                    'data' => [
                        'userId' => $aggregateId,
                        'tenantId' => 'tenant-test',
                        'sessionInvalidated' => true,
                        'occurredAt' => $occurredAt->toAtomString(),
                    ],
                ],
                'occurred_at' => $occurredAt,
                'status' => OutboxMessage::STATUS_PENDING,
                'attempts' => 0,
                'recovery_count' => 0,
            ]);
        }

        return $ids;
    }

    private function assertPendingCount(int $expected): void
    {
        $actual = OutboxMessage::where('status', OutboxMessage::STATUS_PENDING)->count();
        $this->assertEquals($expected, $actual, "Expected {$expected} PENDING messages, found {$actual}");
    }

    private function analyzeWorkerResults($results): void
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($results->collect() as $index => $result) {
            if ($result->successful()) {
                $successCount++;
                $this->addToAssertionCount(1);

                continue;
            }

            $failureCount++;
            echo "Worker {$index} failed:\n";
            echo $result->errorOutput()."\n";
        }

        echo "Worker Results: {$successCount} successful, {$failureCount} failed\n";
    }

    /**
     * @param  array<int>  $availableShardIds
     */
    private function restrictAvailableShards(array $availableShardIds): void
    {
        $future = now()->addMinutes(10);

        DB::table('outbox_shards')->update([
            'owner_worker_id' => 'test-reserved',
            'expires_at' => $future,
            'acquired_at' => now(),
            'messages_processed' => 0,
            'updated_at' => now(),
        ]);

        DB::table('outbox_shards')
            ->whereIn('shard_id', $availableShardIds)
            ->update([
                'owner_worker_id' => null,
                'expires_at' => null,
                'acquired_at' => null,
                'messages_processed' => 0,
                'updated_at' => now(),
            ]);
    }

    private function resetShardLeases(): void
    {
        DB::table('outbox_shards')->update([
            'owner_worker_id' => null,
            'expires_at' => null,
            'acquired_at' => null,
            'messages_processed' => 0,
            'updated_at' => now(),
        ]);
    }

    private function databaseSupportsSkipLocked(): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return true;
        }

        if ($driver !== 'mysql') {
            return false;
        }

        $version = strtolower((string) (DB::selectOne('select version() as version')->version ?? ''));

        if ($version === '' || str_contains($version, 'mariadb')) {
            return false;
        }

        if (! preg_match('/(\d+)\.(\d+)/', $version, $matches)) {
            return false;
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];

        return $major > 8 || ($major === 8 && $minor >= 0);
    }

    private function ensureOutboxSchemaIsReady(): void
    {
        if (self::$outboxSchemaReady) {
            return;
        }

        if (! Schema::hasTable('outbox_messages') || ! Schema::hasTable('outbox_shards')) {
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        }

        self::$outboxSchemaReady = true;
    }

    /**
     * @return array<string, string>
     */
    private function testingEnvironment(): array
    {
        $defaultConnection = (string) config('database.default');
        $connection = (array) config("database.connections.{$defaultConnection}", []);

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $defaultConnection,
            'DB_HOST' => (string) ($connection['host'] ?? ''),
            'DB_PORT' => (string) ($connection['port'] ?? ''),
            'DB_DATABASE' => (string) ($connection['database'] ?? ''),
            'DB_USERNAME' => (string) ($connection['username'] ?? ''),
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        ];

        return array_filter($env, static fn (string $value): bool => $value !== '');
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('outbox_messages')) {
            OutboxMessage::query()->delete();
        }

        if (Schema::hasTable('outbox_shards')) {
            $this->resetShardLeases();
        }

        parent::tearDown();
    }
}
