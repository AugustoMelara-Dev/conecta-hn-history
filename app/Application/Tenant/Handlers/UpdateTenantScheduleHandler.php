<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\UpdateTenantScheduleCommand;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * UpdateTenantScheduleHandler - Business Hours Update Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DOMAIN RULE: At least one day must be open.                            ║
 * ║  HTTP AGNOSTIC: No Illuminate\Http references.                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateTenantScheduleHandler
{
    use LogSanitizer;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle schedule update command.
     *
     * @throws ModelNotFoundException If tenant not found
     * @throws DomainException If no day is open
     */
    public function handle(UpdateTenantScheduleCommand $command): void
    {
        // 1. Load Tenant
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw new ModelNotFoundException("Tenant not found: {$command->tenantId}");
        }

        // 2. DOMAIN RULE: At least one day must be open
        $this->validateAtLeastOneDayOpen($command->scheduleData);

        // 3. Update schedule field
        $tenant->schedule = $command->scheduleData;

        // 4. Persist via Repository
        $this->tenantRepository->save($tenant);

        $this->logger->info('Tenant schedule updated', $this->sanitizePayload([
            'tenant_id' => $command->tenantId,
        ], $this->scrubber));
    }

    /**
     * Validate that at least one day has open hours.
     *
     * @throws DomainException
     */
    private function validateAtLeastOneDayOpen(array $schedule): void
    {
        $hasOpenDay = collect($schedule)
            ->contains(fn (array $day) => ($day['isOpen'] ?? false) === true);

        if (! $hasOpenDay) {
            throw new DomainException('El negocio debe tener al menos un día abierto.');
        }
    }
}
