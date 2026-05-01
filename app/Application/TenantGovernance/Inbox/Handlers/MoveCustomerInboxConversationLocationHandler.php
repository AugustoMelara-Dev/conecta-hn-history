<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Inbox\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface;
use App\Application\TenantGovernance\Inbox\Commands\MoveCustomerInboxConversationLocationCommand;
use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Models\CustomerNote;
use App\Models\Location;
use RuntimeException;

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

        if (! $this->locationBelongsToTenant($command->tenantId, $command->locationId)) {
            throw new RuntimeException('La sede seleccionada no pertenece a este negocio.');
        }

        return $this->ops->moveToLocation($note, $command->locationId, $command->actorUserId);
    }

    private function locationBelongsToTenant(string $tenantId, ?string $locationId): bool
    {
        if (! filled($locationId)) {
            return true;
        }

        return Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($locationId)
            ->exists();
    }
}
