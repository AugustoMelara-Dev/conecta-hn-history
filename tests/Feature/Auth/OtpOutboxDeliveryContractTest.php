<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Common\Contracts\CircuitBreakerInterface;
use App\Application\Shared\Contracts\SensitivePayloadCipherInterface;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Auth\Listeners\SendOtpNotificationListener;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

final class OtpOutboxDeliveryContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function encrypted_otp_is_decrypted_only_inside_its_tenant_listener(): void
    {
        $tenant = Tenant::factory()->create();
        $cipher = $this->createMock(SensitivePayloadCipherInterface::class);
        $cipher->expects(self::once())->method('decrypt')->with('ciphertext')->willReturn('123456');
        $circuit = $this->createMock(CircuitBreakerInterface::class);
        $circuit->expects(self::once())->method('isAvailable')->with('sms_provider')->willReturn(true);
        $circuit->expects(self::once())->method('reportSuccess')->with('sms_provider');
        $notifier = $this->createMock(OtpNotifierInterface::class);
        $notifier->expects(self::once())->method('send')->with(
            null,
            '123456',
            'otp@example.test',
            (string) $tenant->getKey(),
            15,
        );
        $event = OtpGenerated::now(
            otpId: 'otp-1',
            userId: null,
            tenantId: (string) $tenant->getKey(),
            encryptedCode: 'ciphertext',
            identifier: 'otp@example.test',
            recipientName: 'Cliente',
            expiryMinutes: 15,
        );

        self::assertStringNotContainsString('123456', json_encode($event->toPayload(), JSON_THROW_ON_ERROR));

        $listener = new SendOtpNotificationListener($notifier, $circuit, $cipher);
        $middleware = $listener->middleware($event);
        $middleware[0]->handle(new stdClass, static function () use ($listener, $event): void {
            $listener->handle($event);
        });

        self::assertFalse(app(TenantContext::class)->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
    }
}
