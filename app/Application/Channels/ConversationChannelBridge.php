<?php

declare(strict_types=1);

namespace App\Application\Channels;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Domain\Shared\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class ConversationChannelBridge
{
    private const CONSENT_PURPOSE = 'conversation_continuity';

    public function __construct(
        private ConversationStore $conversations,
        private ChannelConnectionService $connections,
        private ChannelIdentityResolver $identities,
        private ChannelConsentService $consents,
        private TenantContext $tenantContext,
    ) {}

    public function resolveOrCreate(
        string $tenantId,
        ChannelContext $context,
        NormalizedInboundMessage $message,
        ?string $conversationId = null,
    ): string {
        $tenantId = strtolower(trim($tenantId));
        if (! Str::isUuid($tenantId)
            || $tenantId !== strtolower($context->tenantId)
            || $tenantId !== strtolower($message->tenantId)) {
            throw new AuthorizationException('Channel conversation tenant lineage is invalid.');
        }
        $this->assertTenantContext($tenantId);
        $context->assertNormalizedInboundLineage($message);
        if ($conversationId !== null && ! Str::isUuid($conversationId)) {
            throw new InvalidArgumentException('Conversation ID must be a UUID.');
        }

        $identityHash = $this->identityHash($message);
        $providerEventId = $message->providerEventId ?? $message->externalMessageId;
        $payloadHash = $this->payloadHash($message);
        $replayed = $this->existingByProviderEvent($tenantId, $context, $providerEventId, $payloadHash);
        if ($replayed !== null) {
            return $replayed->conversationId;
        }

        // A webhook claim can commit before the conversation/link transaction.
        // If that boundary is interrupted, retry the deterministic conversation
        // claim below so a later delivery can repair the missing lineage.
        $this->connections->claimWebhook($context, $message);

        $identity = $this->identities->resolve($context, $message);
        $consent = $this->consents->record($context, $identity, $message, self::CONSENT_PURPOSE);
        $conversationId = $this->resolveContinuity(
            tenantId: $tenantId,
            context: $context,
            identityHash: $identityHash,
            consentStatus: $consent->status,
            requestedConversationId: $conversationId,
            externalThreadReference: $message->externalThreadReference,
        );

        $conversationThreadReference = $message->externalThreadReference;
        $originType = $this->originType($context->channel);
        $locale = $context->locale;
        $marketCountryCode = $context->marketCountryCode;
        $currency = $context->currency;
        $timezone = $context->timezone;
        $actorId = $identity->id;
        if ($conversationId !== null) {
            $existingConversation = DB::table('ai_conversations')
                ->where('tenant_id', $tenantId)
                ->where('id', $conversationId)
                ->first([
                    'actor_id',
                    'origin_type',
                    'locale',
                    'market_country_code',
                    'currency',
                    'timezone',
                ]);
            if ($existingConversation === null) {
                throw new AuthorizationException('Conversation does not belong to the tenant.');
            }
            $actorId = (string) ($existingConversation->actor_id ?: $identity->id);
            $originType = (string) $existingConversation->origin_type;
            $locale = (string) ($existingConversation->locale ?: $locale);
            $marketCountryCode = (string) ($existingConversation->market_country_code ?: $marketCountryCode);
            $currency = (string) ($existingConversation->currency ?: $currency);
            $timezone = (string) ($existingConversation->timezone ?: $timezone);
            if ($conversationThreadReference === null) {
                $conversationThreadReference = DB::table('dovra_conversation_channel_links')
                    ->where('tenant_id', $tenantId)
                    ->where('conversation_id', $conversationId)
                    ->whereNotNull('external_thread_reference')
                    ->orderBy('last_inbound_at')
                    ->value('external_thread_reference');
            }
        }

        $claim = $this->conversations->claim(new AcceptConversationMessage(
            tenantId: $tenantId,
            visibility: 'tenant_private',
            originType: $originType,
            externalThreadReference: $conversationThreadReference,
            actorId: $actorId,
            locale: $locale,
            marketCountryCode: $marketCountryCode,
            currency: $currency,
            timezone: $timezone,
            content: $message->content,
            contentType: 'text',
            idempotencyKey: $this->inboundIdempotencyKey($message),
            conversationId: $conversationId,
        ));

        $this->persistInboundLink(
            tenantId: $tenantId,
            context: $context,
            message: $message,
            conversationId: $claim->conversationId,
            identityHash: $identityHash,
            consentStatus: $consent->status,
            providerEventId: $providerEventId,
            payloadHash: $payloadHash,
        );

        return $claim->conversationId;
    }

    public function linkOutbound(string $tenantId, string $conversationId, ChannelOutboundMessage $message): void
    {
        $tenantId = strtolower(trim($tenantId));
        if (! Str::isUuid($tenantId)
            || $tenantId !== strtolower($message->tenantId)
            || ! Str::isUuid($conversationId)) {
            throw new AuthorizationException('Outbound conversation tenant lineage is invalid.');
        }
        $this->assertTenantContext($tenantId);
        DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->exists() || throw new AuthorizationException('Conversation does not belong to the tenant.');

        $now = now();
        $channel = strtolower($message->channel);
        $payloadHash = hash('sha256', json_encode([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'channel' => $channel,
            'connection_key' => $message->connectionKey,
            'recipient' => strtolower(trim($message->recipient)),
            'content_hash' => hash('sha256', $message->content),
            'external_thread_reference' => $message->externalThreadReference,
            'metadata' => $message->metadata,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $lineage = DB::table('dovra_conversation_channel_links')
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('connection_key', $message->connectionKey)
            ->where('lineage_idempotency_key', $message->idempotencyKey)
            ->first();
        if ($lineage !== null) {
            if ((string) $lineage->conversation_id !== $conversationId
                || ! hash_equals((string) $lineage->payload_hash, $payloadHash)) {
                throw new ChannelConflictException('Outbound channel lineage idempotency conflict.');
            }
            DB::table('dovra_conversation_channel_links')->where('id', $lineage->id)->update([
                'last_outbound_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('dovra_conversation_channel_links')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'channel' => $channel,
            'connection_key' => $message->connectionKey,
            'normalized_identity_hash' => hash('sha256', 'recipient|'.strtolower(trim($message->recipient))),
            'external_thread_reference' => $message->externalThreadReference,
            'consent_purpose' => self::CONSENT_PURPOSE,
            'consent_status' => null,
            'external_message_id' => null,
            'provider_event_id' => null,
            'lineage_idempotency_key' => $message->idempotencyKey,
            'payload_hash' => $payloadHash,
            'last_outbound_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $stored = DB::table('dovra_conversation_channel_links')
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('connection_key', $message->connectionKey)
            ->where('lineage_idempotency_key', $message->idempotencyKey)
            ->first();
        if ($stored === null
            || (string) $stored->conversation_id !== $conversationId
            || ! hash_equals((string) $stored->payload_hash, $payloadHash)) {
            throw new ChannelConflictException('Outbound channel lineage idempotency conflict.');
        }
        /*
         * Keep the lineage table free of message bodies. The payload hash above
         * only binds recipient/thread/metadata to the idempotency key.
         */
    }

    private function assertTenantContext(string $tenantId): void
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            throw new AuthorizationException('A concrete tenant context is required for channel conversations.');
        }
        try {
            if (! hash_equals(strtolower($this->tenantContext->id()), $tenantId)) {
                throw new AuthorizationException('Channel conversation tenant context mismatch.');
            }
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthorizationException('Channel conversation tenant context is unavailable.', previous: $exception);
        }
    }

    private function resolveContinuity(
        string $tenantId,
        ChannelContext $context,
        string $identityHash,
        ?string $consentStatus,
        ?string $requestedConversationId,
        ?string $externalThreadReference,
    ): ?string {
        if ($requestedConversationId !== null) {
            $requested = DB::table('dovra_conversation_channel_links')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $requestedConversationId)
                ->where('normalized_identity_hash', $identityHash)
                ->where('consent_status', 'granted')
                ->exists();
            if (! $requested || $consentStatus !== 'granted') {
                throw new AuthorizationException('Conversation continuation requires matching identity consent.');
            }

            return $requestedConversationId;
        }

        $sameChannel = DB::table('dovra_conversation_channel_links')
            ->where('tenant_id', $tenantId)
            ->where('channel', $context->channel)
            ->where('connection_key', $context->connectionKey)
            ->where('normalized_identity_hash', $identityHash)
            ->when($externalThreadReference !== null, fn ($query) => $query->where('external_thread_reference', $externalThreadReference))
            ->when($externalThreadReference === null, fn ($query) => $query->whereNull('external_thread_reference'))
            ->orderByDesc('last_inbound_at')
            ->first();
        if ($sameChannel !== null) {
            return (string) $sameChannel->conversation_id;
        }

        if ($consentStatus !== 'granted') {
            return null;
        }

        $crossChannel = DB::table('dovra_conversation_channel_links')
            ->where('tenant_id', $tenantId)
            ->where('normalized_identity_hash', $identityHash)
            ->where('consent_status', 'granted')
            ->orderByDesc('last_inbound_at')
            ->first();

        return $crossChannel === null ? null : (string) $crossChannel->conversation_id;
    }

    private function existingByProviderEvent(
        string $tenantId,
        ChannelContext $context,
        string $providerEventId,
        string $payloadHash,
    ): ?ConversationChannelLink {
        $row = DB::table('dovra_conversation_channel_links')
            ->where('tenant_id', $tenantId)
            ->where('channel', $context->channel)
            ->where('connection_key', $context->connectionKey)
            ->where('provider_event_id', $providerEventId)
            ->first();
        if ($row === null) {
            return null;
        }
        if (! hash_equals((string) $row->payload_hash, $payloadHash)) {
            throw new RuntimeException('Channel webhook payload conflicts with stored lineage.');
        }

        return $this->linkFromRow($row);
    }

    private function persistInboundLink(
        string $tenantId,
        ChannelContext $context,
        NormalizedInboundMessage $message,
        string $conversationId,
        string $identityHash,
        ?string $consentStatus,
        string $providerEventId,
        string $payloadHash,
    ): void {
        $now = now();
        DB::table('dovra_conversation_channel_links')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'channel' => $context->channel,
                'connection_key' => $context->connectionKey,
                'external_message_id' => $message->externalMessageId,
            ],
            [
                'id' => (string) (DB::table('dovra_conversation_channel_links')
                    ->where('tenant_id', $tenantId)
                    ->where('channel', $context->channel)
                    ->where('connection_key', $context->connectionKey)
                    ->where('external_message_id', $message->externalMessageId)
                    ->value('id') ?: Str::uuid()),
                'conversation_id' => $conversationId,
                'normalized_identity_hash' => $identityHash,
                'external_thread_reference' => $message->externalThreadReference,
                'consent_purpose' => self::CONSENT_PURPOSE,
                'consent_status' => $consentStatus,
                'provider_event_id' => $providerEventId,
                'lineage_idempotency_key' => $this->inboundIdempotencyKey($message),
                'payload_hash' => $payloadHash,
                'last_inbound_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function linkFromRow(object $row): ConversationChannelLink
    {
        return new ConversationChannelLink(
            id: (string) $row->id,
            tenantId: (string) $row->tenant_id,
            conversationId: (string) $row->conversation_id,
            channel: (string) $row->channel,
            connectionKey: (string) $row->connection_key,
            normalizedIdentityHash: (string) $row->normalized_identity_hash,
            externalThreadReference: $row->external_thread_reference === null ? null : (string) $row->external_thread_reference,
            consentPurpose: (string) $row->consent_purpose,
            consentStatus: $row->consent_status === null ? null : (string) $row->consent_status,
            externalMessageId: $row->external_message_id === null ? null : (string) $row->external_message_id,
            providerEventId: $row->provider_event_id === null ? null : (string) $row->provider_event_id,
            lineageIdempotencyKey: (string) $row->lineage_idempotency_key,
            payloadHash: (string) $row->payload_hash,
            lastInboundAt: $row->last_inbound_at === null ? null : (string) $row->last_inbound_at,
            lastOutboundAt: $row->last_outbound_at === null ? null : (string) $row->last_outbound_at,
        );
    }

    private function identityHash(NormalizedInboundMessage $message): string
    {
        return hash('sha256', strtolower(trim($message->identityType)).'|'.strtolower(trim($message->normalizedIdentity)));
    }

    private function payloadHash(NormalizedInboundMessage $message): string
    {
        return hash('sha256', json_encode([
            'tenant_id' => $message->tenantId,
            'channel' => $message->channel,
            'connection_key' => $message->connectionKey,
            'external_message_id' => $message->externalMessageId,
            'provider_event_id' => $message->providerEventId,
            'external_thread_reference' => $message->externalThreadReference,
            'identity_type' => $message->identityType,
            'normalized_identity' => $message->normalizedIdentity,
            'content_hash' => hash('sha256', $message->content),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function inboundIdempotencyKey(NormalizedInboundMessage $message): string
    {
        return 'channel-inbound:'.hash('sha256', implode('|', [
            strtolower($message->tenantId),
            $message->channel,
            $message->connectionKey,
            $message->providerEventId ?? $message->externalMessageId,
        ]));
    }

    private function originType(string $channel): string
    {
        return match (strtolower($channel)) {
            'whatsapp' => 'whatsapp',
            'mobile' => 'mobile',
            'web' => 'web',
            default => 'api',
        };
    }
}
