<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class TenantCustomerProfileEditReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceReader $workspaceReader,
    ) {}

    public function subheading(): string
    {
        return 'Ajusta notas, opt-ins, etiquetas, tareas y continuidad con una lectura 360 del cliente.';
    }

    /**
     * @return array<int, string>
     */
    public function duplicateOptions(CustomerProfile $profile, string $search): array
    {
        return $this->workspaceReader->duplicateOptions($profile, $search);
    }

    public function duplicateLabel(CustomerProfile $profile, mixed $value): ?string
    {
        return $this->workspaceReader->duplicateLabel($profile, $value);
    }
}
