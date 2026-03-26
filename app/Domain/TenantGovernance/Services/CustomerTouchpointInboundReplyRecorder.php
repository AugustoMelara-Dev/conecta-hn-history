<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerConversationRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerConversation;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class CustomerTouchpointInboundReplyRecorder
{
    public function __construct(
        private readonly CustomerConversationOpsManager $conversationOpsManager,
        private readonly CustomerJourneyManager $journeyManager,
        private readonly CustomerNoteRepositoryInterface $notes,
        private readonly CustomerProfileRepositoryInterface $profiles,
        private readonly CustomerConversationRepositoryInterface $conversations,
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

        $profile = $this->profiles->findWithoutScopes((int) $touchpoint->customer_profile_id);

        if ($profile instanceof CustomerProfile) {
            $profile->forceFill([
                'last_inbound_reply_at' => $occurredAt,
                'last_engaged_channel' => $channel,
            ]);

            $this->profiles->save($profile);
        }

        if ($channel === 'whatsapp') {
            $this->applyOptInKeywords($touchpoint, $message);
        }

        $this->syncJourneyAfterOutcome($touchpoint);
    }

    public function syncTouchpoint(CustomerTouchpoint $touchpoint): void
    {
        $this->syncJourneyAfterOutcome($touchpoint);
    }

    private function applyOptInKeywords(CustomerTouchpoint $touchpoint, string $body): void
    {
        $keyword = strtoupper(trim($body));
        $accept = collect((array) config('customer-automation.opt_in_keywords.accept', []))
            ->map(static fn (mixed $value): string => strtoupper((string) $value))
            ->all();
        $stop = collect((array) config('customer-automation.opt_in_keywords.stop', []))
            ->map(static fn (mixed $value): string => strtoupper((string) $value))
            ->all();

        if (! in_array($keyword, [...$accept, ...$stop], true)) {
            return;
        }

        $accepting = in_array($keyword, $accept, true);
        $profile = $this->profiles->findWithoutScopes((int) $touchpoint->customer_profile_id);

        if (! $profile instanceof CustomerProfile) {
            return;
        }

        $profile->forceFill([
            'whatsapp_opt_in' => $accepting,
            'marketing_opt_in' => $accepting ? true : $profile->marketing_opt_in,
            'last_engaged_channel' => 'whatsapp',
            'last_inbound_reply_at' => now(),
        ]);
        $this->profiles->save($profile);

        $this->notes->create([
            'tenant_id' => (string) $touchpoint->tenant_id,
            'customer_profile_id' => (int) $touchpoint->customer_profile_id,
            'note_type' => 'opt_in_update',
            'status' => 'resolved',
            'priority' => 'low',
            'source_channel' => 'whatsapp',
            'requires_follow_up' => false,
            'handled_at' => now(),
            'body' => $accepting
                ? 'El cliente confirmo opt-in por WhatsApp.'
                : 'El cliente solicito baja de WhatsApp.',
            'metadata' => [
                'provider' => 'whatsapp_cloud',
                'keyword' => $keyword,
                'accepted' => $accepting,
            ],
        ]);
    }

    private function syncJourneyAfterOutcome(CustomerTouchpoint $touchpoint): void
    {
        $conversation = $touchpoint->customer_conversation_id !== null
            ? $this->conversations->findWithoutScopes((int) $touchpoint->customer_conversation_id)
            : null;
        $profile = $this->profiles->findWithoutScopes((int) $touchpoint->customer_profile_id)?->loadMissing('tenant');

        if ($conversation instanceof CustomerConversation && $profile instanceof CustomerProfile) {
            $this->journeyManager->sync($conversation, $profile);
        }
    }
}
