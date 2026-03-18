<?php

namespace Tests\Infrastructure\Idempotency;

use App\Infrastructure\Common\Idempotency\AtomicIdempotencyService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ZombieLockTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_recovers_stale_processing_locks()
    {
        // 0. Freeze Time
        $fixedNow = \Carbon\Carbon::create(2025, 1, 1, 12, 0, 0);
        \Carbon\Carbon::setTestNow($fixedNow);

        // 1. Arrange: Create a "Zombie" lock (Stale PROCESSING)
        $key = 'ZOMBIE-KEY-123';
        $today = now(); // Uses TestNow
        $twoHoursAgo = $today->copy()->subHours(2);

        DB::table('processed_commands')->insert([
            'idempotency_key' => $key,
            'tenant_id' => 'tenant-1',
            'command_hash' => 'hash-x',
            'status' => 'PROCESSING',
            'created_at' => $twoHoursAgo,
            'updated_at' => $twoHoursAgo, // EXPIRED (> 60s)
        ]);

        $service = $this->app->make(AtomicIdempotencyService::class);

        // 2. Act: Attempt to execute with the same key
        $executed = false;
        $result = $service->execute($key, 'tenant-1', 'hash-x', function () use (&$executed) {
            $executed = true;

            return 'FRESH_RESULT';
        });

        // 3. Assert
        $this->assertTrue($executed, ' The operation should have executed after recovering the lock.');
        $this->assertEquals('FRESH_RESULT', $result);

        // Verify DB State
        $record = DB::table('processed_commands')->where('idempotency_key', $key)->first();
        $this->assertEquals('COMPLETED', $record->status);
        $this->assertNotEquals($twoHoursAgo->toIso8601String(), \Carbon\Carbon::parse($record->updated_at)->toIso8601String());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throttles_active_processing_locks()
    {
        // 0. Freeze Time
        $fixedNow = \Carbon\Carbon::create(2025, 1, 1, 12, 0, 0);
        \Carbon\Carbon::setTestNow($fixedNow);

        // 1. Arrange: Create an "Active" lock (Fresh PROCESSING)
        $key = 'ACTIVE-KEY-123';
        $now = now();

        DB::table('processed_commands')->insert([
            'idempotency_key' => $key,
            'tenant_id' => 'tenant-1',
            'command_hash' => 'hash-x',
            'status' => 'PROCESSING',
            'created_at' => $now,
            'updated_at' => $now, // FRESH (< 60s)
        ]);

        $service = $this->app->make(AtomicIdempotencyService::class);

        // 2. Act & Assert: Expect Exception
        $this->expectException(\App\Application\Shared\Common\Exceptions\ConcurrentRequestException::class);

        $service->execute($key, 'tenant-1', 'hash-x', function () {
            return 'SHOULD_NOT_EXECUTE';
        });
    }
}