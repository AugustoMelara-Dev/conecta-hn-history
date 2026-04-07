<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Tenant\Commands\UpdateTenantPanelSettingsCommand;
use App\Application\Tenant\Contracts\TenantProfileWriterInterface;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;

final readonly class UpdateTenantPanelSettingsHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantProfileWriterInterface $tenantProfileWriter,
    ) {}

    /**
     * @throws TenantNotFoundException
     */
    public function handle(UpdateTenantPanelSettingsCommand $command): void
    {
        if ($this->tenantRepository->findById($command->tenantId) === null) {
            throw TenantNotFoundException::withId($command->tenantId);
        }

        $this->tenantProfileWriter->updatePanelSettings($command->tenantId, [
            'name' => trim($command->name),
            'whatsapp_number' => trim($command->whatsappNumber),
            'description' => $this->nullableString($command->description),
            'address' => $this->nullableString($command->address),
            'whatsapp_message' => trim($command->whatsappMessage),
        ]);
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
