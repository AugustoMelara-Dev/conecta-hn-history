<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Commands;

final readonly class TakeCustomerInboxConversationCommand
{
    public function __construct(
        public string $tenantId,
        public int $noteId,
        public ?string $actorUserId,
    ) {}
}
