<?php

namespace Tests\Integration\Auth;

use App\Application\Common\Contracts\AtomicIdempotencyInterface;
use App\Application\Common\Exceptions\ConcurrentRequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdempotencyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private AtomicIdempotencyInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(AtomicIdempotencyInterface::class);
    }

    /** @test */
    public function it_can_connect_to_db()
    {
        $this->assertTrue(DB::connection() instanceof \Illuminate\Database\ConnectionInterface);
    }

    /** @test */
    public function it_has_processed_commands_table()
    {
        $this->assertTrue(Schema::hasTable('processed_commands'), 'Table processed_commands should exist.');
    }

    /** @test */
    public function it_enforces_payload_versioning_governance()
    {
        $key = 'test-key-v1';
        $tenantId = 'uuid-tenant-1';
        $hash = 'test-hash';

        // 1. Simulate "Old Version" Payload (Cache Poisoning)
        DB::table('processed_commands')->insert([
            'idempotency_key' => $key,
            'tenant_id' => $tenantId,
            'command_hash' => $hash,
            'status' => 'COMPLETED',
            'response_payload' => json_encode(['old_structure' => true]), // Missing 'v' key
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Execute Service with same key
        // Expectation: Service detects version mismatch, deletes old record, throws Exception to force Client Retry
        $this->expectException(ConcurrentRequestException::class);
        $this->expectExceptionMessage('Idempotency schema version mismatch');

        $this->service->execute($key, $tenantId, $hash, fn () => 'New Result');

        // 3. Verify Flush
        $this->assertDatabaseMissing('processed_commands', ['idempotency_key' => $key]);
    }

    /** @test */
    public function it_implements_fail_safe_mechanism_on_domain_exception()
    {
        $key = 'test-key-fail';
        $tenantId = 'uuid-tenant-2';
        $hash = 'test-hash-2';

        try {
            $this->service->execute($key, $tenantId, $hash, function () {
                throw new \DomainException('Business Logic Failure');
            });
        } catch (\DomainException $e) {
            // Expected
        }

        // 3. Verify Lock Release (Rollback)
        // The record 'PROCESSING' should be gone.
        $this->assertDatabaseMissing('processed_commands', ['idempotency_key' => $key]);
    }

    /** @test */
    public function it_prunes_stale_records_with_select_delete_strategy()
    {
        // 1. Arrange: Seed 5 Old and 5 New records
        // Old records (Should be deleted)
        for ($i = 1; $i <= 5; $i++) {
            DB::table('processed_commands')->insert([
                'idempotency_key' => "old-{$i}",
                'tenant_id' => 't1',
                'command_hash' => 'h',
                'status' => 'COMPLETED',
                'created_at' => now()->subDays(31),
                'updated_at' => now(),
            ]);
        }

        // Fresh records (Should remain)
        for ($i = 1; $i <= 5; $i++) {
            DB::table('processed_commands')->insert([
                'idempotency_key' => "fresh-{$i}",
                'tenant_id' => 't1',
                'command_hash' => 'h',
                'status' => 'COMPLETED',
                // Use explicit 'now' to match exact boundary logic if needed, but today is safe
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Act: Run Command
        $this->artisan('auth:prune-idempotency', ['--days' => 30])
            ->assertExitCode(0);

        // 3. Assert (Strict Integrity)
        $remainingCount = DB::table('processed_commands')->count();
        $this->assertEquals(5, $remainingCount, 'Should have exactly 5 records remaining');

        // Assert remaining are the fresh ones
        $remainingKeys = DB::table('processed_commands')->pluck('idempotency_key')->toArray();
        foreach ($remainingKeys as $key) {
            $this->assertStringStartsWith('fresh-', $key, "Remaining key {$key} should be fresh");
        }
    }
}
