<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Tenant\Commands\UpdateTenantMarketingWhatsAppCommand;
use App\Application\Tenant\Contracts\TenantProfileWriterInterface;
use App\Domain\Tenant\Exceptions\FeatureRequiresProException;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;

final readonly class UpdateTenantMarketingWhatsAppHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantProfileWriterInterface $tenantProfileWriter,
    ) {}

    /**
     * @throws FeatureRequiresProException
     * @throws TenantNotFoundException
     */
    public function handle(UpdateTenantMarketingWhatsAppCommand $command): void
    {
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw TenantNotFoundException::withId($command->tenantId);
        }

        if (! $tenant->isPro()) {
            throw new FeatureRequiresProException('Marketing WhatsApp configuration');
        }

        $this->tenantProfileWriter->updateMarketingSettings($command->tenantId, [
            'whatsapp_number' => preg_replace('/[^0-9]/', '', $command->whatsappNumber) ?? '',
            'whatsapp_message' => trim($command->whatsappMessage),
        ]);
    }
}
