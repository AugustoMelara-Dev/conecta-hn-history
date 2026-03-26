<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerTouchpointEvent;

class CustomerTouchpointEventRecorder
{
    public function __construct(
        private readonly ResendTouchpointEventProcessor $resendProcessor,
        private readonly WhatsAppTouchpointEventProcessor $whatsAppProcessor,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function recordResendWebhook(array $payload, array $headers = []): ?CustomerTouchpointEvent
    {
        return $this->resendProcessor->record($payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function recordWhatsAppWebhook(array $payload, array $headers = []): int
    {
        $tracked = 0;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $statusPayload) {
                    $tracked += $this->whatsAppProcessor->recordStatus($value, (array) $statusPayload, $headers) ? 1 : 0;
                }

                foreach ((array) ($value['messages'] ?? []) as $messagePayload) {
                    $tracked += $this->whatsAppProcessor->recordInboundMessage($value, (array) $messagePayload, $headers) ? 1 : 0;
                }
            }
        }

        return $tracked;
    }
}
