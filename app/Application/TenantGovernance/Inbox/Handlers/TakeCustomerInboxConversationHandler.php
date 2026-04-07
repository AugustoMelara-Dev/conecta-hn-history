<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface;
use App\Application\TenantGovernance\Inbox\Commands\TakeCustomerInboxConversationCommand;
use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Models\CustomerNote;

final readonly class TakeCustomerInboxConversationHandler
{
    public function __construct(
        private TenantCustomerInboxNoteFinderInterface $notes,
        private CustomerConversationOpsManager $ops,
    ) {}

    public function handle(TakeCustomerInboxConversationCommand $command): ?CustomerNote
    {
        $note = $this->notes->findNote($command->tenantId, $command->noteId);

        if (! $note instanceof CustomerNote) {
            return null;
        }

        return $this->ops->take($note, $command->actorUserId);
    }
}
