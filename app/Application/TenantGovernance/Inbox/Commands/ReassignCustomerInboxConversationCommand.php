<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Commands;

final readonly class ReassignCustomerInboxConversationCommand
{
    public function __construct(
        public string $tenantId,
        public int $noteId,
        public ?string $ownerUserId,
        public ?string $actorUserId,
    ) {}
}
