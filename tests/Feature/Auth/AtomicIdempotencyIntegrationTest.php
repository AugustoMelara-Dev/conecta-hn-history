<?php

namespace Tests\Feature\Auth;

use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Common\Exceptions\IdempotencyKeyReuseException;
use App\Infrastructure\Common\Idempotency\AtomicIdempotencyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class AtomicIdempotencyIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private AtomicIdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolve the service from container to ensure bindings are correct
        $this->service = $this->app->make(AtomicIdempotencyService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_successfully_executes_locks_and_completes_operation()
    {
        $key = Uuid::uuid7()->toString();
        $tenantId = Uuid::uuid7()->toString();
        $hash = hash('sha256', 'payload');

        $result = $this->service->execute($key, $tenantId, $hash, function () {
            return ['status' => 'ok'];
        });

        $this->assertEquals(['status' => 'ok'], $result);

        // Verify Database State
        // Verify Database State
        $record = DB::table('processed_commands')->where('idempotency_key', $key)->first();
        $this->assertNotNull($record, 'Record not found in DB');
        $this->assertEquals('COMPLETED', $record->status);

        $payload = json_decode($record->response_payload, true);
        $this->assertEquals(['status' => 'ok'], $payload['data'], 'Payload data mismatch');
        $this->assertEquals(1, $payload['v'], 'Payload version mismatch');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_replay_with_different_hash()
    {
        $key = Uuid::uuid7()->toString();
        $tenantId = Uuid::uuid7()->toString();
        $hash1 = hash('sha256', 'payload1');
        $hash2 = hash('sha256', 'payload2');

        // First Execution
        $this->service->execute($key, $tenantId, $hash1, fn () => 'success');

        // Second Execution with altered payload/hash
        $this->expectException(IdempotencyKeyReuseException::class);
        $this->service->execute($key, $tenantId, $hash2, fn () => 'hacker');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_cached_result_on_replay_with_same_hash()
    {
        $key = Uuid::uuid7()->toString();
        $tenantId = Uuid::uuid7()->toString();
        $hash = hash('sha256', 'payload');

        // First Execution
        $result1 = $this->service->execute($key, $tenantId, $hash, function () {
            // Check that we are indeed in PROCESSING state inside the closure?
            // This is hard to assert without a separate unchecked connection.
            return ['data' => 123];
        });

        // Second Execution (Replay)
        $result2 = $this->service->execute($key, $tenantId, $hash, function () {
            $this->fail('Should not execute domain logic on replay');
        });

        $this->assertEquals($result1, $result2);
        $this->assertEquals(['data' => 123], $result2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_concurrent_exception_when_row_is_locked_processing()
    {
        $key = Uuid::uuid7()->toString();
        $tenantId = Uuid::uuid7()->toString();
        $hash = hash('sha256', 'payload');

        // Manually insert a PROCESSING record (Simulation of Race Winner)
        DB::table('processed_commands')->insert([
            'idempotency_key' => $key,
            'tenant_id' => $tenantId,
            'command_hash' => $hash,
            'status' => 'PROCESSING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt to execute (Race Loser)
        $this->expectException(ConcurrentRequestException::class);

        $this->service->execute($key, $tenantId, $hash, function () {
            $this->fail('Should not execute domain logic when locked');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_cleans_up_processing_state_on_domain_failure()
    {
        $key = Uuid::uuid7()->toString();
        $tenantId = Uuid::uuid7()->toString();
        $hash = hash('sha256', 'payload');

        try {
            $this->service->execute($key, $tenantId, $hash, function () {
                throw new \RuntimeException('Domain Failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Assert that the 'PROCESSING' row is GONE (Rolled Back)
        // This confirms the "Fail-Safe" mechanism works
        $this->assertDatabaseMissing('processed_commands', [
            'idempotency_key' => $key,
        ]);

        // Verify we can retry successfully
        $result = $this->service->execute($key, $tenantId, $hash, fn () => 'Success Retry');

        $this->assertEquals('Success Retry', $result);
    }
}
