<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class CustomerTouchpointInboundReplyRecorder
{
    public function __construct(
        private readonly CustomerConversationOpsManager $conversationOpsManager,
        private readonly CustomerInboundProfileUpdater $profileUpdater,
        private readonly CustomerTouchpointOptInKeywordProcessor $optInKeywordProcessor,
        private readonly CustomerTouchpointConversationSynchronizer $conversationSynchronizer,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        CustomerTouchpoint $touchpoint,
        string $body,
        Carbon $occurredAt,
        string $provider,
        string $channel,
        array $metadata = [],
    ): void {
        $message = trim($body);

        $this->conversationOpsManager->recordInboundReply(
            $touchpoint,
            $message !== '' ? $message : 'Respuesta recibida del cliente.',
            $occurredAt,
            $channel,
            [
                'provider' => $provider,
                ...$metadata,
            ],
        );

        $this->profileUpdater->touchInboundChannel($touchpoint, $occurredAt, $channel);

        if ($channel === 'whatsapp') {
            $this->optInKeywordProcessor->process($touchpoint, $message);
        }

        $this->conversationSynchronizer->sync($touchpoint);
    }
}
