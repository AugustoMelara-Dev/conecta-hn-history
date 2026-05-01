<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface;
use App\Application\TenantGovernance\Inbox\Commands\ReassignCustomerInboxConversationCommand;
use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Models\CustomerNote;
use App\Models\TenantUser;
use RuntimeException;

final readonly class ReassignCustomerInboxConversationHandler
{
    public function __construct(
        private TenantCustomerInboxNoteFinderInterface $notes,
        private CustomerConversationOpsManager $ops,
    ) {}

    public function handle(ReassignCustomerInboxConversationCommand $command): ?CustomerNote
    {
        $note = $this->notes->findNote($command->tenantId, $command->noteId);

        if (! $note instanceof CustomerNote) {
            return null;
        }

        if (! $this->ownerBelongsToTenant($command->tenantId, $command->ownerUserId)) {
            throw new RuntimeException('El owner seleccionado no pertenece a este negocio.');
        }

        return $this->ops->reassign($note, $command->ownerUserId, $command->actorUserId);
    }

    private function ownerBelongsToTenant(string $tenantId, ?string $ownerUserId): bool
    {
        if (! filled($ownerUserId)) {
            return true;
        }

        return TenantUser::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $ownerUserId)
            ->exists();
    }
}
