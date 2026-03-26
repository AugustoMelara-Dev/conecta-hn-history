<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardUrlMapReader
{
    /**
     * @return array<string, string>
     */
    public function read(callable $safeRoute): array
    {
        return [
            'tenants' => $safeRoute('filament.admin.resources.tenants.index'),
            'users' => $safeRoute('filament.admin.resources.users.index'),
            'payments' => $safeRoute('filament.admin.resources.payments.index'),
            'settings' => $safeRoute('filament.admin.pages.manage-general-settings'),
            'billing' => $safeRoute('filament.admin.pages.billing'),
            'operations' => $safeRoute('filament.admin.resources.operational-playbook-tasks.index'),
        ];
    }
}
