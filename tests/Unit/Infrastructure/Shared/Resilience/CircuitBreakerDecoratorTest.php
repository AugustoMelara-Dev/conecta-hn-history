<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Shared\Resilience;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Infrastructure\Shared\Resilience\CircuitBreakerDecorator;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * CircuitBreakerDecoratorTest - White Box Logic Verification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ISOLATION: 100% Mocked. No Redis. No Network.                          ║
 * ║  PRECISION: Verifies exact call counts and state transitions.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class CircuitBreakerDecoratorTest extends TestCase
{
    private const FAILURE_THRESHOLD = 5;

    private const OPEN_TTL = 60;

    private const SERVICE_NAME = 'test_service';

    private const CACHE_KEY_FAILURES = 'circuit_breaker:otp_notifier:test_service:failures';

    private const CACHE_KEY_OPEN = 'circuit_breaker:otp_notifier:test_service:open';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\Group('resilience')]
    #[\PHPUnit\Framework\Attributes\Group('unit')]
    public function test_it_opens_circuit_and_stops_calling_service_after_reaching_failure_threshold(): void
    {
        // 1. ARRANGE: Mocks
        /** @var OtpNotifierInterface|MockInterface $innerService */
        $innerService = Mockery::mock(OtpNotifierInterface::class);

        /** @var CacheRepository|MockInterface $cache */
        $cache = Mockery::mock(CacheRepository::class);

        $decorator = new CircuitBreakerDecorator(
            $innerService,
            $cache,
            self::SERVICE_NAME
        );

        // 2. ACT & ASSERT PHASE 1: SATURATION LOOP (Threshold = 5)
        for ($i = 1; $i <= self::FAILURE_THRESHOLD; $i++) {
            // EXPECTATIONS FOR EACH ITERATION:

            // A. Check if open -> returns false (Circuit Closed)
            $cache->shouldReceive('has')
                ->with(self::CACHE_KEY_OPEN)
                ->once()
                ->andReturn(false);

            // B. Inner service called -> throws Exception (Simulating Collapse)
            $innerService->shouldReceive('send')
                ->once()
                ->andThrow(new Exception("Provider Timeout Scenario iteration #{$i}"));

            // C. Record Failure -> Increment
            $cache->shouldReceive('increment')
                ->with(self::CACHE_KEY_FAILURES)
                ->once()
                ->andReturn($i); // Return current count (1, 2, 3, 4, 5)

            // D. First failure sets expiry for the failure window
            if ($i === 1) {
                $cache->shouldReceive('put')
                    ->with(self::CACHE_KEY_FAILURES, 1, 120)
                    ->once();
            }

            // E. TRIP LOGIC (Only on Nth iteration)
            if ($i === self::FAILURE_THRESHOLD) {
                // Should trip circuit
                $cache->shouldReceive('put')
                    ->with(self::CACHE_KEY_OPEN, true, self::OPEN_TTL)
                    ->once();

                // Should reset failure counter
                $cache->shouldReceive('forget')
                    ->with(self::CACHE_KEY_FAILURES)
                    ->once();
            }

            // EXECUTE
            try {
                $decorator->send(null, '123456', 'user@example.com', 'tenant_1', 15);
                $this->fail("Should have thrown exception on iteration {$i}");
            } catch (Exception $e) {
                $this->assertEquals("Provider Timeout Scenario iteration #{$i}", $e->getMessage());
            }
        }

        // 3. ACT & ASSERT PHASE 2: FAIL FAST (Iteration N+1)

        // A. Check if open -> returns TRUE (Circuit Open)
        $cache->shouldReceive('has')
            ->with(self::CACHE_KEY_OPEN)
            ->once()
            ->andReturn(true);

        // B. Inner Service MUST NOT be called (The Protecion)
        $innerService->shouldReceive('send')->never();

        // EXECUTE
        try {
            $decorator->send(null, '123456', 'user@example.com', 'tenant_1', 15);
            $this->fail('Should have thrown RuntimeException (Circuit Open)');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Circuit Opened', $e->getMessage());
            $this->assertStringContainsString(self::SERVICE_NAME, $e->getMessage());
        }
    }
}
