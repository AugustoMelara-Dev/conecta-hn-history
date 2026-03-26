<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\CustomerNote;

interface CustomerNoteRepositoryInterface
{
    public function create(array $attributes): CustomerNote;

    public function save(CustomerNote $note): CustomerNote;

    public function latestOpenConversationNote(int|string $tenantId, int|string $profileId, string $channel): ?CustomerNote;

    public function latestRecentConversationNote(int|string $tenantId, int|string $profileId, string $channel, int $windowHours): ?CustomerNote;
}
