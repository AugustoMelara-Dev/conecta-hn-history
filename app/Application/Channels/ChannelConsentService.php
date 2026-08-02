<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChannelConsentService
{
    public function record(
        ChannelContext $context,
        ResolvedChannelIdentity $identity,
        NormalizedInboundMessage $message,
        string $purpose,
    ): ChannelConsentRecord {
        $existing = DB::table('channel_consents')
            ->where('tenant_id', $context->tenantId)
            ->where('channel', $context->channel)
            ->where('identity_id', $identity->id)
            ->where('purpose', $purpose)
            ->first();

        $id = is_object($existing) ? (string) $existing->id : (string) Str::uuid();
        $status = $message->consentStatus ?? 'unknown';
        $now = now();

        DB::table('channel_consents')->updateOrInsert(
            [
                'tenant_id' => $context->tenantId,
                'channel' => $context->channel,
                'identity_id' => $identity->id,
                'purpose' => $purpose,
            ],
            [
                'id' => $id,
                'status' => $status,
                'source' => 'inbound',
                'market_country_code' => $context->marketCountryCode,
                'connection_key' => $context->connectionKey,
                'evidence' => json_encode($message->metadata, JSON_THROW_ON_ERROR),
                'granted_at' => $status === 'granted' ? $now : $existing?->granted_at,
                'revoked_at' => $status === 'revoked' ? $now : null,
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        return new ChannelConsentRecord($id, $status, $purpose);
    }
}
