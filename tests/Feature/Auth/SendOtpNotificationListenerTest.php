<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Auth\Contracts\OtpDeliveryPayloadStoreInterface;
use App\Application\Shared\Contracts\CircuitBreakerInterface;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Infrastructure\Auth\Listeners\SendOtpNotificationListener;
use App\Models\OneTimePassword;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
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

        $this->mock(OtpNotifierInterface::class, function (MockInterface $mock) use ($tenantId): void {
            $mock->shouldReceive('send')
                ->once()
                ->with(null, '123456', 'test@example.com', $tenantId, 10);
        });

        $this->mock(CircuitBreakerInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')
                ->once()
                ->with('email_provider')
                ->andReturnTrue();

            $mock->shouldReceive('reportSuccess')
                ->once()
                ->with('email_provider');

            $mock->shouldNotReceive('reportFailure');
        });

        $listener = $this->app->make(SendOtpNotificationListener::class);

        $listener->handle(new OtpGenerated(
            otpId: (string) $otp->id,
            userId: null,
            tenantId: $tenantId,
            occurredAt: new \DateTimeImmutable,
        ));

        $this->assertNull($store->get((string) $otp->id));
    }
}
