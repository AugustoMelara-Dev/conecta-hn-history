<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Auth\Contracts\OtpDeliveryPayloadStoreInterface;
use App\Application\Shared\Contracts\CircuitBreakerInterface;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Identity\Entities\UserEntity;
use App\Infrastructure\Auth\Listeners\SendOtpNotificationListener;
use App\Models\OneTimePassword;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SendOtpNotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_delivers_the_otp_using_the_ephemeral_payload_store(): void
    {
        $tenantId = (string) Tenant::factory()->create()->id;

        $otp = OneTimePassword::query()->create([
            'user_id' => null,
            'identifier' => 'test@example.com',
            'tenant_id' => $tenantId,
            'code' => 'hashed-otp',
            'type' => 'email',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $store = $this->app->make(OtpDeliveryPayloadStoreInterface::class);
        $store->put($otp->id, '123456', 'test@example.com', 10);

        $notifier = new RecordingOtpNotifier();
        $circuitBreaker = new RecordingCircuitBreaker();

        $this->app->instance(OtpNotifierInterface::class, $notifier);
        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        $listener = $this->app->make(SendOtpNotificationListener::class);

        $listener->handle(new OtpGenerated(
            otpId: (string) $otp->id,
            userId: null,
            tenantId: $tenantId,
            occurredAt: new \DateTimeImmutable,
        ));

        self::assertSame([['service' => 'email_provider']], $circuitBreaker->availabilityChecks);
        self::assertSame([['service' => 'email_provider']], $circuitBreaker->successfulReports);
        self::assertSame([], $circuitBreaker->failedReports);
        self::assertSame('123456', $notifier->plainTextCode);
        self::assertSame('test@example.com', $notifier->identifier);
        self::assertSame($tenantId, $notifier->tenantId);
        self::assertSame(10, $notifier->expiryMinutes);
        self::assertNull($notifier->recipient);
        self::assertNull($store->get((string) $otp->id));
    }
}

final class RecordingOtpNotifier implements OtpNotifierInterface
{
    public ?UserEntity $recipient = null;

    public ?string $plainTextCode = null;

    public ?string $identifier = null;

    public ?string $tenantId = null;

    public ?int $expiryMinutes = null;

    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes
    ): void {
        $this->recipient = $recipient;
        $this->plainTextCode = $plainTextCode;
        $this->identifier = $identifier;
        $this->tenantId = $tenantId;
        $this->expiryMinutes = $expiryMinutes;
    }
}

final class RecordingCircuitBreaker implements CircuitBreakerInterface
{
    /** @var array<int, array{service: string}> */
    public array $availabilityChecks = [];

    /** @var array<int, array{service: string}> */
    public array $successfulReports = [];

    /** @var array<int, array{service: string}> */
    public array $failedReports = [];

    public function isAvailable(string $serviceName): bool
    {
        $this->availabilityChecks[] = ['service' => $serviceName];

        return true;
    }

    public function reportSuccess(string $serviceName): void
    {
        $this->successfulReports[] = ['service' => $serviceName];
    }

    public function reportFailure(string $serviceName): void
    {
        $this->failedReports[] = ['service' => $serviceName];
    }
}
