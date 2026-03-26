<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class ResendTouchpointOutcomeApplier
{
    public function __construct(
        private readonly CustomerTouchpointGovernance $governance,
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly CustomerTouchpointConversationSynchronizer $conversationSynchronizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(CustomerTouchpoint $touchpoint, string $eventType, array $payload, Carbon $occurredAt): void
    {
        $touchpoint->forceFill([
            'delivery_status' => $this->deliveryStatusForEvent($eventType),
            'provider' => 'resend',
            'provider_payload' => $payload,
        ]);

        match ($eventType) {
            'email.sent' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->sent_at ??= $occurredAt),
            'email.delivered' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->delivered_at = $occurredAt),
            'email.opened' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->opened_at = $occurredAt),
            'email.clicked' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->clicked_at = $occurredAt),
            'email.delivery_delayed' => $this->markDeliveryDelayed($touchpoint, $occurredAt),
            'email.bounced' => $this->markFailed($touchpoint, $occurredAt, $payload, 'Rebote del proveedor de correo.'),
            'email.complained' => $this->markFailed($touchpoint, $occurredAt, $payload, 'El destinatario reporto una queja del correo.', false),
            'email.received', 'email.replied' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->replied_at = $occurredAt),
            default => null,
        };

        if ($touchpoint->status === CustomerTouchpoint::STATUS_QUEUED) {
            $touchpoint->status = CustomerTouchpoint::STATUS_SENT;
        }

        $this->touchpoints->save($touchpoint);
        $this->conversationSynchronizer->sync($touchpoint);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markFailed(CustomerTouchpoint $touchpoint, Carbon $occurredAt, array $payload, string $fallbackMessage, bool $scheduleRetry = true): void
    {
        $touchpoint->status = CustomerTouchpoint::STATUS_FAILED;
        $touchpoint->failed_at = $occurredAt;
        $touchpoint->last_error = (string) data_get($payload, 'data.reason', $fallbackMessage);
        $touchpoint->next_retry_at = $scheduleRetry ? $this->governance->nextRetryAt($touchpoint, $occurredAt) : null;
        if (str_contains((string) ($payload['type'] ?? ''), 'bounce')) {
            $touchpoint->bounced_at = $occurredAt;
        } else {
            $touchpoint->complained_at = $occurredAt;
        }
    }

    private function markDeliveryHealthy(CustomerTouchpoint $touchpoint, \Closure $mutate): void
    {
        $mutate();
        $touchpoint->next_retry_at = null;
        if ($touchpoint->status === CustomerTouchpoint::STATUS_FAILED) {
            $touchpoint->status = CustomerTouchpoint::STATUS_SENT;
        }
    }

    private function markDeliveryDelayed(CustomerTouchpoint $touchpoint, Carbon $occurredAt): void
    {
        $touchpoint->last_error = 'La entrega del correo se retraso en el proveedor.';
        $touchpoint->next_retry_at = $this->governance->nextRetryAt($touchpoint, $occurredAt);
    }

    private function deliveryStatusForEvent(string $eventType): string
    {
        return match ($eventType) {
            'email.delivered' => 'delivered',
            'email.opened' => 'opened',
            'email.clicked' => 'clicked',
            'email.bounced' => 'bounced',
            'email.complained' => 'complained',
            'email.received', 'email.replied' => 'replied',
            'email.delivery_delayed' => 'delayed',
            default => 'sent',
        };
    }
}
