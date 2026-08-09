<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\SubscriptionExtensionFailedNotification;
use PHPUnit\Framework\TestCase;

final class SubscriptionExtensionFailedNotificationTest extends TestCase
{
    public function test_it_is_provider_independent_and_contains_only_safe_operational_context(): void
    {
        $notification = new SubscriptionExtensionFailedNotification('payment-1', 'tenant-1');

        self::assertSame(['database'], $notification->via(new \stdClass));
        self::assertSame([
            'type' => 'subscription_extension_failed',
            'title' => 'Fallo al extender una suscripción',
            'message' => 'La aprobación del pago requiere revisión manual.',
            'payment_id' => 'payment-1',
            'tenant_id' => 'tenant-1',
        ], $notification->toArray(new \stdClass));
    }
}
