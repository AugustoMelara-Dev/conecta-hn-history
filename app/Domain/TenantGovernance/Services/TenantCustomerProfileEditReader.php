<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class TenantCustomerProfileEditReader
{
    public function __construct(
        private readonly CustomerProfileDuplicateReader $duplicateReader,
        private readonly CustomerProfileSectionReader $sectionReader,
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
        return $this->duplicateReader->options($profile, $search);
    }

    public function duplicateLabel(CustomerProfile $profile, mixed $value): ?string
    {
        return $this->duplicateReader->selectedLabel($profile, $value);
    }

    /**
     * @return array<string, string|int|float|null>
     */
    public function formState(CustomerProfile $profile): array
    {
        return [
            ...$this->sectionReader->profileSummary($profile),
            ...$this->sectionReader->loyaltySummary($profile),
        ];
    }
}
