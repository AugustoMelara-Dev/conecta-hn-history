<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

final readonly class UpdateTenantPublicProfileCommand
{
    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<int, array<string, mixed>>|null  $hoursData
     */
    public function __construct(
        public string $tenantId,
        public array $profileData,
        public ?array $hoursData = null,
        public int $schemaVersion = 1,
    ) {}
}
