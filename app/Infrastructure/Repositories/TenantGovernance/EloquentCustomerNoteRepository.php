<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface;
use App\Models\CustomerNote;

final class EloquentCustomerNoteRepository implements CustomerNoteRepositoryInterface
{
    public function create(array $attributes): CustomerNote
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->create($attributes);
    }

    public function save(CustomerNote $note): CustomerNote
    {
        $note->save();

        return $note->fresh();
    }

    public function latestOpenConversationNote(int|string $tenantId, int|string $profileId, string $channel): ?CustomerNote
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantId)
            ->where('customer_profile_id', (int) $profileId)
            ->where('source_channel', $channel)
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('last_customer_message_at')
            ->orderByDesc('created_at')
            ->first();
    }

    public function latestRecentConversationNote(int|string $tenantId, int|string $profileId, string $channel, int $windowHours): ?CustomerNote
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantId)
            ->where('customer_profile_id', (int) $profileId)
            ->where('source_channel', $channel)
            ->whereIn('status', ['open', 'in_progress', 'pending_customer'])
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->orderByDesc('last_customer_message_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
