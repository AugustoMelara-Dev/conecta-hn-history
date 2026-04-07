<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface;
use App\Application\TenantGovernance\Inbox\Commands\SendCustomerInboxTemplateCommand;
use App\Domain\TenantGovernance\Services\CustomerConversationTemplateManager;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;

final readonly class SendCustomerInboxTemplateHandler
{
    public function __construct(
        private TenantCustomerInboxNoteFinderInterface $notes,
        private CustomerConversationTemplateManager $templates,
    ) {}

    public function handle(SendCustomerInboxTemplateCommand $command): ?CustomerTouchpoint
    {
        $note = $this->notes->findNote($command->tenantId, $command->noteId);

        if (! $note instanceof CustomerNote) {
            return null;
        }

        $profile = $note->customerProfile;

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        return $this->templates->send(
            profile: $profile,
            templateKey: $command->templateKey,
            note: $note,
            actorUserId: $command->actorUserId,
        );
    }
}
