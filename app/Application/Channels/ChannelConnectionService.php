<?php

declare(strict_types=1);

namespace App\Application\Channels;

use App\Application\Channels\Contracts\ChannelAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChannelConnectionService
{
    private readonly ChannelAuthorizationService $authorization;

    public function __construct(?ChannelAuthorizationService $authorization = null)
    {
        $this->authorization = $authorization ?? new ChannelAuthorizationService;
    }

    public function connect(ChannelContext $context): ChannelConnection
    {
        $this->authorization->assertAuthorized($context, 'connect');

        $existing = DB::table('channel_connections')
            ->where('tenant_id', $context->tenantId)
            ->where('channel', $context->channel)
            ->where('connection_key', $context->connectionKey)
            ->first();

        $id = is_object($existing) ? (string) $existing->id : (string) Str::uuid();
        $providerKey = $context->providerKey($context->channel.'.connection');
        $now = now();

        DB::table('channel_connections')->updateOrInsert(
            [
                'tenant_id' => $context->tenantId,
                'channel' => $context->channel,
                'connection_key' => $context->connectionKey,
            ],
            [
                'id' => $id,
                'provider_key' => $providerKey,
                'market_country_code' => $context->marketCountryCode,
                'secret_reference_key' => $this->secretReferenceKey($context),
                'config' => json_encode($this->safeConnectionConfig($context->connection), JSON_THROW_ON_ERROR),
                'status' => 'active',
                'connected_at' => $existing?->connected_at ?? $now,
                'updated_at' => $now,
                'created_at' => $existing?->created_at ?? $now,
            ],
        );

        return new ChannelConnection(
            id: $id,
            tenantId: $context->tenantId,
            channel: $context->channel,
            connectionKey: $context->connectionKey,
            providerKey: $providerKey,
            status: 'active',
        );
    }

    public function snapshotCapability(ChannelAdapterInterface $adapter, ChannelContext $context): ChannelCapabilityDecision
    {
        $connection = $this->connect($context);
        $decision = $adapter->capability($context);
        $now = now();

        DB::table('channel_capability_snapshots')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $context->tenantId,
            'channel' => $context->channel,
            'connection_key' => $context->connectionKey,
            'adapter_key' => $decision->adapterKey,
            'provider_key' => $decision->providerKey,
            'market_country_code' => $context->marketCountryCode,
            'allowed' => $decision->allowed,
            'reason' => $decision->reason,
            'snapshot' => json_encode($decision->metadata, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('channel_provider_health')->updateOrInsert(
            [
                'tenant_id' => $context->tenantId,
                'channel' => $context->channel,
                'connection_key' => $context->connectionKey,
            ],
            [
                'id' => (string) (DB::table('channel_provider_health')
                    ->where('tenant_id', $context->tenantId)
                    ->where('channel', $context->channel)
                    ->where('connection_key', $context->connectionKey)
                    ->value('id') ?: Str::uuid()),
                'provider_key' => $connection->providerKey,
                'status' => $decision->allowed ? 'available' : 'unavailable',
                'reason' => $decision->reason,
                'checked_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        return $decision;
    }

    public function claimWebhook(ChannelContext $context, NormalizedInboundMessage $message): bool
    {
        $this->authorization->assertAuthorized($context, 'claim webhook');
        $context->assertNormalizedInboundLineage($message);
        $claimHash = WebhookAuthenticityEnvelope::authenticatedClaimHash($message);
        $this->connect($context);
        $providerEventId = $message->providerEventId ?? $message->externalMessageId;
        $payloadHash = $claimHash
            ?? (string) ($message->metadata[WebhookAuthenticityEnvelope::META_PAYLOAD_HASH] ?? hash('sha256', $providerEventId));
        $now = now();

        return DB::table('channel_webhook_claims')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $context->tenantId,
            'channel' => $context->channel,
            'connection_key' => $context->connectionKey,
            'provider_event_id' => $providerEventId,
            'payload_hash' => $payloadHash,
            'claimed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }

    /** @param array<string, mixed> $connection */
    private function safeConnectionConfig(array $connection): array
    {
        $safe = $connection;

        foreach (array_keys($safe) as $key) {
            $normalized = strtolower($key);
            if (str_contains($normalized, 'secret')
                || str_contains($normalized, 'token')
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'key')) {
                unset($safe[$key]);
            }
        }

        if (($connection['provider'] ?? null) !== null) {
            $safe['provider'] = $connection['provider'];
        }
        if (($connection['display_name'] ?? null) !== null) {
            $safe['display_name'] = $connection['display_name'];
        }

        return $safe;
    }

    private function secretReferenceKey(ChannelContext $context): ?string
    {
        $reference = $context->connection['secret_reference_key'] ?? null;

        return is_string($reference) && trim($reference) !== '' ? trim($reference) : null;
    }
}
