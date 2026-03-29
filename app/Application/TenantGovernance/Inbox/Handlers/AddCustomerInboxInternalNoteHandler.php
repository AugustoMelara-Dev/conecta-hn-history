<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface;
use App\Application\TenantGovernance\Inbox\Commands\AddCustomerInboxInternalNoteCommand;
use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Models\CustomerConversationMessage;
use App\Models\CustomerNote;

final readonly class AddCustomerInboxInternalNoteHandler
{
    public function __construct(
        private TenantCustomerInboxNoteFinderInterface $notes,
        private CustomerConversationOpsManager $ops,
    ) {}

    public function handle(AddCustomerInboxInternalNoteCommand $command): ?CustomerConversationMessage
    {
        $note = $this->notes->findNote($command->tenantId, $command->noteId);

        if (! $note instanceof CustomerNote) {
            return null;
        }

        return $this->ops->addInternalNote($note, $command->body, $command->actorUserId);
    }
}
