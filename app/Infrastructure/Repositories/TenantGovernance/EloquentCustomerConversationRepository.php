<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CustomerConversationRepositoryInterface;
use App\Models\CustomerConversation;
use App\Models\CustomerConversationMessage;
use Illuminate\Support\Carbon;

final class EloquentCustomerConversationRepository implements CustomerConversationRepositoryInterface
{
    public function findByConversationKey(string $tenantId, int $profileId, string $conversationKey): ?CustomerConversation
    {
        return CustomerConversation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->where('conversation_key', $conversationKey)
            ->first();
    }

    public function findRecentOpen(string $tenantId, int $profileId, string $channel, Carbon $since): ?CustomerConversation
    {
        return CustomerConversation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->where('source_channel', $channel)
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->where('updated_at', '>=', $since)
            ->latest('updated_at')
            ->first();
    }

    public function create(array $attributes): CustomerConversation
    {
        return CustomerConversation::query()
            ->withoutGlobalScopes()
            ->create($attributes);
    }

    public function save(CustomerConversation $conversation): void
    {
        $conversation->save();
    }

    public function createMessage(array $attributes): CustomerConversationMessage
    {
        return CustomerConversationMessage::query()
            ->withoutGlobalScopes()
            ->create($attributes);
    }

    public function countMessagesByDirections(int $conversationId, array $directions): int
    {
        return CustomerConversationMessage::query()
            ->withoutGlobalScopes()
            ->where('customer_conversation_id', $conversationId)
            ->whereIn('direction', $directions)
            ->count();
    }
}
