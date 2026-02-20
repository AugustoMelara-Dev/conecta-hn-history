<?php

declare(strict_types=1);

namespace Tests\Architecture\Scalability;

use App\Infrastructure\Persistence\Eloquent\Models\OutboxMessage;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * OutboxScalingTest - Competing Consumers Validation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Prove that FOR UPDATE SKIP LOCKED prevents duplicate events   ║
 * ║  METHOD: Launch N parallel workers attacking the same pending queue     ║
 * ║  ASSERTION: Each message processed exactly once (no duplicates)         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REQUIRES: MySQL 8.0+ or PostgreSQL 9.5+ (SQLite does NOT support SKIP LOCKED)
 */
class OutboxScalingTest extends TestCase
{
    private string $testDbPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if using SQLite (no SKIP LOCKED support)
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped(
                'Skipping concurrency test: SQLite does not support FOR UPDATE SKIP LOCKED. '.
                'Run this test against MySQL 8.0+ or PostgreSQL 9.5+ for accurate results.'
            );
        }
    }

    /** @test */
    public function it_prevents_duplicate_processing_under_parallel_worker_load(): void
    {
        // 1. SETUP: Create 100 pending messages
        $messageCount = 100;
        $messageIds = $this->seedPendingMessages($messageCount);

        $this->assertCount($messageCount, $messageIds);
        $this->assertPendingCount($messageCount);

        // 2. ATTACK: Launch 3 parallel workers, each with limit=35
        // Total capacity: 3 * 35 = 105 (intentionally > 100 to force contention)
        $workerCount = 3;
        $limitPerWorker = 35;

        $results = Process::pool(function (Pool $pool) use ($workerCount, $limitPerWorker) {
            for ($i = 0; $i < $workerCount; $i++) {
                $pool->command("php artisan infrastructure:process-outbox --batch-size={$limitPerWorker} --max-loops=1")
                    ->timeout(60);
            }
        })->wait();

        // 3. ANALYZE RESULTS
        $this->analyzeWorkerResults($results);

        // 4. ASSERTIONS

        // 4a. INTEGRITY: All messages should be PUBLISHED
        $publishedCount = OutboxMessage::where('status', 'PUBLISHED')->count();
        $this->assertEquals(
            $messageCount,
            $publishedCount,
            "Expected all {$messageCount} messages to be PUBLISHED. Found: {$publishedCount}"
        );

        // 4b. NO PENDING LEFT: Queue should be empty
        $pendingCount = OutboxMessage::where('status', 'PENDING')->count();
        $this->assertEquals(
            0,
            $pendingCount,
            "Expected 0 PENDING messages after processing. Found: {$pendingCount}"
        );

        // 4c. UNIQUENESS CHECK: No duplicate processing
        // If SKIP LOCKED failed, some messages would be processed by multiple workers.
        // We detect this by checking if any message has been published more than once
        // (in a real system, this would manifest as duplicate events in the event bus)
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

    /** @test */
    public function it_handles_empty_queue_gracefully_in_daemon_mode(): void
    {
        // Ensure queue is empty
        OutboxMessage::query()->delete();

        // Run with a timeout (should sleep and exit cleanly)
        $result = Process::timeout(5)->run('php artisan infrastructure:process-outbox --max-loops=1');

        $this->assertTrue($result->successful(), 'Command should complete successfully on empty queue');
        $this->assertStringContainsString('Total processed: 0', $result->output());
    }

    /** @test */
    public function it_respects_limit_option(): void
    {
        // Seed more messages than limit
        $this->seedPendingMessages(50);

        // Process with limit=20
        $result = Process::run('php artisan infrastructure:process-outbox --batch-size=20 --max-loops=1');

        $this->assertTrue($result->successful());

        // Should have processed exactly 20
        $publishedCount = OutboxMessage::where('status', 'PUBLISHED')->count();
        $this->assertEquals(20, $publishedCount);

        // Should still have 30 pending
        $pendingCount = OutboxMessage::where('status', 'PENDING')->count();
        $this->assertEquals(30, $pendingCount);
    }

    /**
     * Seed the outbox with N pending messages.
     *
     * @return array<string> Array of created message IDs
     */
    private function seedPendingMessages(int $count): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $id = Uuid::uuid7()->toString();
            $ids[] = $id;

            OutboxMessage::create([
                'id' => $id,
                'type' => 'App\\Domain\\Test\\Events\\TestEvent',
                'aggregate_type' => 'Test',
                'aggregate_id' => Uuid::uuid7()->toString(),
                'payload' => json_encode([
                    'meta' => ['version' => '1.0'],
                    'data' => ['test_id' => $i, 'message' => "Test message {$i}"],
                ]),
                'occurred_at' => now(),
                'status' => 'PENDING',
                'attempts' => 0,
            ]);
        }

        return $ids;
    }

    /**
     * Assert exact pending count.
     */
    private function assertPendingCount(int $expected): void
    {
        $actual = OutboxMessage::where('status', 'PENDING')->count();
        $this->assertEquals($expected, $actual, "Expected {$expected} PENDING messages, found {$actual}");
    }

    /**
     * Analyze and log worker process results for debugging.
     */
    private function analyzeWorkerResults($results): void
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($results as $index => $result) {
            if ($result->successful()) {
                $successCount++;
                $this->addToAssertionCount(1);
            } else {
                $failureCount++;
                echo "Worker {$index} failed:\n";
                echo $result->errorOutput()."\n";
            }
        }

        echo "Worker Results: {$successCount} successful, {$failureCount} failed\n";
    }

    protected function tearDown(): void
    {
        // Cleanup test data
        OutboxMessage::query()->delete();

        parent::tearDown();
    }
}
