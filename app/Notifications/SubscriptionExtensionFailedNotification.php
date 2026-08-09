<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

final class SubscriptionExtensionFailedNotification extends Notification
{
    public function __construct(
        private readonly string $paymentId,
        private readonly string $tenantId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_extension_failed',
            'title' => 'Fallo al extender una suscripción',
            'message' => 'La aprobación del pago requiere revisión manual.',
            'payment_id' => $this->paymentId,
            'tenant_id' => $this->tenantId,
        ];
    }
}
