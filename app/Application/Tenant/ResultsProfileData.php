<?php

declare(strict_types=1);

namespace App\Application\Tenant;

/**
 * ResultsProfileData - Data Transfer Object for AI Profile Enhancement Jobs
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Job-layer DTO that carries the tenant identifier for queue    ║
 * ║  transport. The EnhanceTenantProfileJob receives this DTO and wraps     ║
 * ║  it into an EnhanceTenantProfileCommand inside its handle() method.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ResultsProfileData
{
    public function __construct(
        public string $tenantId,
        public ?string $description = null,
        public array $tags = [],
        public bool $isAiGenerated = false,
    ) {}
}
