<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class ResendInboundReplyHandler
{
    public function __construct(
        private readonly CustomerTouchpointInboundReplyRecorder $inboundReplies,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(CustomerTouchpoint $touchpoint, array $payload, Carbon $occurredAt, string $providerMessageId, array $tags): void
    {
        $this->inboundReplies->record(
            $touchpoint,
            (string) data_get($payload, 'data.text', data_get($payload, 'data.subject', 'Respuesta recibida por email.')),
            $occurredAt,
            'resend',
            'email',
            [
                'source_touchpoint_id' => (int) $touchpoint->getKey(),
                'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : $touchpoint->provider_message_id,
                'provider_thread_key' => (string) ($tags['provider_thread_key']
                    ?? data_get($touchpoint->metadata, 'provider_thread_key')
                    ?? data_get($touchpoint->metadata, 'conversation_key')
                    ?? $touchpoint->provider_message_id
                    ?? ''),
                'provider_reply_to' => (string) data_get($payload, 'data.in_reply_to', $touchpoint->provider_message_id),
            ],
        );
    }
}
