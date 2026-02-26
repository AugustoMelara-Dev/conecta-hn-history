<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

/**
 * UpdateTenantScheduleCommand - Immutable Input for Schedule Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSPORT ONLY: No validation logic - Handler validates business rules.║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateTenantScheduleCommand
{
    public function __construct(
        /** Tenant ID to update */
        public int $tenantId,

        /**
         * Weekly schedule data structure.
         * Expected format: [['day' => 'monday', 'isOpen' => true, 'open' => '08:00', 'close' => '17:00'], ...]
         */
        public array $scheduleData,

        /** Schema Version for Upcasting */
        public int $schemaVersion = 1,
    ) {}
}
