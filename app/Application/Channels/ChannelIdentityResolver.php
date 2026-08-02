<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChannelIdentityResolver
{
    public function resolve(ChannelContext $context, NormalizedInboundMessage $message): ResolvedChannelIdentity
    {
        $existing = DB::table('channel_identities')
            ->where('tenant_id', $context->tenantId)
            ->where('channel', $context->channel)
            ->where('identity_type', $message->identityType)
            ->where('normalized_value', $message->normalizedIdentity)
            ->first();

        $id = is_object($existing) ? (string) $existing->id : (string) Str::uuid();
        $now = now();

        DB::table('channel_identities')->updateOrInsert(
            [
                'tenant_id' => $context->tenantId,
                'channel' => $context->channel,
                'identity_type' => $message->identityType,
                'normalized_value' => $message->normalizedIdentity,
            ],
            [
                'id' => $id,
                'display_value' => $message->metadata['display_identity'] ?? $message->normalizedIdentity,
                'first_seen_at' => $existing?->first_seen_at ?? $now,
                'last_seen_at' => $now,
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        return new ResolvedChannelIdentity(
            id: $id,
            tenantId: $context->tenantId,
            channel: $context->channel,
            type: $message->identityType,
            normalizedValue: $message->normalizedIdentity,
        );
    }
}
