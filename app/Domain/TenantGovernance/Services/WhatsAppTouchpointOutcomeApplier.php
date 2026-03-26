<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class WhatsAppTouchpointOutcomeApplier
{
    public function __construct(
        private readonly CustomerTouchpointGovernance $governance,
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly CustomerTouchpointInboundReplyRecorder $inboundReplies,
    ) {}

    /**
     * @param  array<string, mixed>  $statusPayload
     * @param  array<string, mixed>  $value
     */
    public function apply(CustomerTouchpoint $touchpoint, array $statusPayload, array $value, Carbon $occurredAt): void
    {
        $status = (string) ($statusPayload['status'] ?? 'sent');

        $touchpoint->forceFill([
            'provider' => 'whatsapp_cloud',
            'provider_payload' => ['value' => $value, 'status' => $statusPayload],
            'delivery_status' => match ($status) {
                'delivered' => CustomerTouchpoint::DELIVERY_DELIVERED,
                'read' => CustomerTouchpoint::DELIVERY_READ,
                'failed' => CustomerTouchpoint::DELIVERY_FAILED,
                default => CustomerTouchpoint::DELIVERY_SENT,
            },
            'sent_at' => $status === 'sent' ? ($touchpoint->sent_at ?? $occurredAt) : $touchpoint->sent_at,
            'delivered_at' => $status === 'delivered' ? $occurredAt : $touchpoint->delivered_at,
            'opened_at' => $status === 'read' ? $occurredAt : $touchpoint->opened_at,
            'failed_at' => $status === 'failed' ? $occurredAt : $touchpoint->failed_at,
            'next_retry_at' => $status === 'failed' ? $this->governance->nextRetryAt($touchpoint, $occurredAt) : null,
            'last_error' => $status === 'failed'
                ? (string) data_get($statusPayload, 'errors.0.title', data_get($statusPayload, 'errors.0.details', 'WhatsApp delivery failed.'))
                : $touchpoint->last_error,
            'status' => $status === 'failed'
                ? CustomerTouchpoint::STATUS_FAILED
                : CustomerTouchpoint::STATUS_SENT,
        ]);

        $this->touchpoints->save($touchpoint);
        $this->inboundReplies->syncTouchpoint($touchpoint);
    }
}
