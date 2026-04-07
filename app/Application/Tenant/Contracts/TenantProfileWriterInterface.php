<?php

declare(strict_types=1);

namespace App\Application\Tenant\Contracts;

interface TenantProfileWriterInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePanelSettings(string $tenantId, array $attributes): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMarketingSettings(string $tenantId, array $attributes): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePublicProfile(string $tenantId, array $attributes): void;

    /**
     * @param  array<int, array<string, mixed>>  $hoursData
     */
    public function syncOpeningHours(string $tenantId, array $hoursData): void;

    public function clearOpeningHours(string $tenantId): void;
}
