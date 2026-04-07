<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Inbox;

use App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface;
use App\Models\CustomerNote;

final class EloquentTenantCustomerInboxNoteFinder implements TenantCustomerInboxNoteFinderInterface
{
    public function findNote(string $tenantId, int $noteId): ?CustomerNote
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with([
                'assignedUser' => fn ($query) => $query->withoutGlobalScopes(),
                'handledBy' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes()->with(['assignedUser', 'location', 'tenant']),
                'conversation' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'owner' => fn ($owner) => $owner->withoutGlobalScopes(),
                    'location' => fn ($location) => $location->withoutGlobalScopes(),
                    'journeyRun',
                ]),
            ])
            ->find($noteId);
    }
}
