<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Commands;

final readonly class SendCustomerInboxTemplateCommand
{
    public function __construct(
        public string $tenantId,
        public int $noteId,
        public string $templateKey,
        public ?string $actorUserId,
    ) {}
}
