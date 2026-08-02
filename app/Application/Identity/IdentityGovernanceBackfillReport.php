<?php

declare(strict_types=1);

namespace App\Application\Identity;

final readonly class IdentityGovernanceBackfillReport
{
    public function __construct(
        public int $ownersMapped,
        public int $acceptedMembersMapped,
        public int $pendingInvitationsRequiringReissue,
        public int $roleConflicts,
    ) {}

    /**
     * @return array{owners_mapped: int, accepted_members_mapped: int, pending_invitations_requiring_reissue: int, role_conflicts: int}
     */
    public function toArray(): array
    {
        return [
            'owners_mapped' => $this->ownersMapped,
            'accepted_members_mapped' => $this->acceptedMembersMapped,
            'pending_invitations_requiring_reissue' => $this->pendingInvitationsRequiringReissue,
            'role_conflicts' => $this->roleConflicts,
        ];
    }
}
