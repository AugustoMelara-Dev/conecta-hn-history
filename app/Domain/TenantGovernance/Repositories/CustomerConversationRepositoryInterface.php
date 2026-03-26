<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\CustomerConversation;
use App\Models\CustomerConversationMessage;
use Illuminate\Support\Carbon;

interface CustomerConversationRepositoryInterface
{
    public function findByConversationKey(string $tenantId, int $profileId, string $conversationKey): ?CustomerConversation;

    public function findRecentOpen(string $tenantId, int $profileId, string $channel, Carbon $since): ?CustomerConversation;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CustomerConversation;

    public function save(CustomerConversation $conversation): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createMessage(array $attributes): CustomerConversationMessage;

    /**
     * @param  list<string>  $directions
     */
    public function countMessagesByDirections(int $conversationId, array $directions): int;
}
