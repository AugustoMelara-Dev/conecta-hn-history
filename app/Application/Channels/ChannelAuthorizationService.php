<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ChannelAuthorizationService
{
    public function assertAuthorized(ChannelContext $context, string $mutation): void
    {
        $authorization = $context->authorization;
        if ($authorization === null) {
            throw new AuthorizationException('Channel mutation requires an actor-bound authorization envelope.');
        }

        $tenant = DB::table('tenants')
            ->where('id', $context->tenantId)
            ->where('is_active', true)
            ->first(['id', 'user_id']);

        if (! is_object($tenant)) {
            throw new AuthorizationException("Channel {$mutation} target tenant is unavailable.");
        }

        if ((string) $tenant->user_id === $authorization->actorId) {
            return;
        }

        $isActiveMember = DB::table('tenant_memberships')
            ->where('tenant_id', $context->tenantId)
            ->where('user_id', $authorization->actorId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->exists();

        if (! $isActiveMember) {
            throw new AuthorizationException(
                "Actor {$authorization->actorId} is not authorized to {$mutation} channel data for tenant {$context->tenantId}.",
            );
        }
    }
}
