<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Shared\Resilience;

use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Infrastructure\Shared\Resilience\CircuitBreakerDecorator;
use Exception;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CircuitBreakerDecoratorTest extends TestCase
{
    private const FAILURE_THRESHOLD = 5;

    private const SERVICE_NAME = 'test_service';

    #[Test]
    #[Group('resilience')]
    #[Group('unit')]
    public function it_opens_circuit_and_stops_calling_service_after_reaching_failure_threshold(): void
    {
        $innerService = new FailingOtpNotifier(self::FAILURE_THRESHOLD);
        $cache = new Repository(new ArrayStore);
        $decorator = new CircuitBreakerDecorator($innerService, $cache, self::SERVICE_NAME);

        for ($i = 1; $i <= self::FAILURE_THRESHOLD; $i++) {
            try {
                $decorator->send(null, '123456', 'user@example.com', 'tenant_1', 15);
                $this->fail("Should have thrown exception on iteration {$i}");
            } catch (Exception $e) {
                $this->assertSame("Provider Timeout Scenario iteration #{$i}", $e->getMessage());
            }
        }

        $this->assertSame(self::FAILURE_THRESHOLD, $innerService->attempts);
        $this->assertTrue($cache->has($this->openKey()));
        $this->assertNull($cache->get($this->failureKey()));

        try {
            $decorator->send(null, '123456', 'user@example.com', 'tenant_1', 15);
            $this->fail('Should have thrown RuntimeException (Circuit Open)');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Circuit Opened', $e->getMessage());
            $this->assertStringContainsString(self::SERVICE_NAME, $e->getMessage());
        }

        $this->assertSame(self::FAILURE_THRESHOLD, $innerService->attempts);
    }

    private function openKey(): string
    {
        return 'circuit_breaker:otp_notifier:'.self::SERVICE_NAME.':open';
    }

    private function failureKey(): string
    {
        return 'circuit_breaker:otp_notifier:'.self::SERVICE_NAME.':failures';
    }
}

final class FailingOtpNotifier implements OtpNotifierInterface
{
    public int $attempts = 0;

    public function __construct(private readonly int $failuresBeforeOpen) {}

    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes
    ): void {
        $this->attempts++;

        if ($this->attempts <= $this->failuresBeforeOpen) {
            throw new Exception("Provider Timeout Scenario iteration #{$this->attempts}");
        }
    }
}
