<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class WhatsAppInboundReplyHandler
{
    public function __construct(
        private readonly CustomerTouchpointInboundReplyRecorder $inboundReplies,
    ) {}

    /**
     * @param  array<string, mixed>  $messagePayload
     */
    public function handle(CustomerTouchpoint $touchpoint, array $messagePayload, Carbon $occurredAt, string $contextMessageId): void
    {
        $body = trim((string) data_get($messagePayload, 'text.body', data_get($messagePayload, 'button.text', 'Respuesta recibida por WhatsApp.')));

        $this->inboundReplies->record(
            $touchpoint,
            $body !== '' ? $body : 'Respuesta recibida por WhatsApp.',
            $occurredAt,
            'whatsapp_cloud',
            'whatsapp',
            [
                'source_touchpoint_id' => (int) $touchpoint->getKey(),
                'from' => (string) ($messagePayload['from'] ?? ''),
                'provider_message_id' => (string) ($messagePayload['id'] ?? ''),
                'provider_thread_key' => (string) (data_get($touchpoint->metadata, 'provider_thread_key')
                    ?? data_get($touchpoint->metadata, 'conversation_key')
                    ?? $contextMessageId
                    ?? ''),
                'provider_reply_to' => $contextMessageId !== '' ? $contextMessageId : null,
            ],
        );
    }
}
