<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Commands;

final readonly class RouteCustomerInboxConversationGroupCommand
{
    public function __construct(
        public string $tenantId,
        public int $noteId,
        public ?string $group,
        public ?string $actorUserId,
    ) {}
}
