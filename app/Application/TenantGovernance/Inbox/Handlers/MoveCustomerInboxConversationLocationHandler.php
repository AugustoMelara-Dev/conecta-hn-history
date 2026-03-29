<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface;
use App\Application\TenantGovernance\Inbox\Commands\MoveCustomerInboxConversationLocationCommand;
use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Models\CustomerNote;

final readonly class MoveCustomerInboxConversationLocationHandler
{
    public function __construct(
        private TenantCustomerInboxNoteFinderInterface $notes,
        private CustomerConversationOpsManager $ops,
    ) {}

    public function handle(MoveCustomerInboxConversationLocationCommand $command): ?CustomerNote
    {
        $note = $this->notes->findNote($command->tenantId, $command->noteId);

        if (! $note instanceof CustomerNote) {
            return null;
        }

        return $this->ops->moveToLocation($note, $command->locationId, $command->actorUserId);
    }
}
