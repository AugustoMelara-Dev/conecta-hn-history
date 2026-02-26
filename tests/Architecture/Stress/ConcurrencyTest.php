<?php

namespace Tests\Architecture\Stress;

use App\Application\Shared\Common\Exceptions\IdempotencyKeyReuseException;
use App\Infrastructure\Common\Idempotency\AtomicIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private AtomicIdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AtomicIdempotencyService::class);
    }

    public function test_it_prevents_duplicate_execution_with_same_idempotency_key()
    {
        $key = 'idemp_123';
        $tenantId = 'tenant_A';
        $hash = 'hash_ABC';

        // 1. First Execution
        $result1 = $this->service->execute($key, $tenantId, $hash, function () {
            return 'RESULT_1';
        });

        $this->assertEquals('RESULT_1', $result1);

        // 2. Second Execution (Same Key) -> Should return CACHED result, NOT execute closure
        $result2 = $this->service->execute($key, $tenantId, $hash, function () {
            return 'RESULT_2_SHOULD_NOT_APPEAR';
        });

        $this->assertEquals('RESULT_1', $result2);
    }

    public function test_it_allows_execution_with_different_keys()
    {
        // 1. First
        $res1 = $this->service->execute('key_1', 't1', 'h1', fn () => 'A');

        // 2. Second
        $res2 = $this->service->execute('key_2', 't1', 'h1', fn () => 'B');

        $this->assertEquals('A', $res1);
        $this->assertEquals('B', $res2);
    }

    public function test_it_throws_exception_on_hash_mismatch()
    {
        $key = 'fixed_key';

        $this->service->execute($key, 't1', 'hash_ORIGINAL', fn () => 'A');

        $this->expectException(IdempotencyKeyReuseException::class);
        $this->expectExceptionMessage('Idempotency key reused with different payload');

        // Reuse key but different hash
        $this->service->execute($key, 't1', 'hash_CHANGED', fn () => 'B');
    }
}
