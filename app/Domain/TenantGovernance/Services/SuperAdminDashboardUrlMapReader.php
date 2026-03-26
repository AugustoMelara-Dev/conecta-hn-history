<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardUrlMapReader
{
    /**
     * @return array<string, string>
     */
    public function read(): array
    {
        return [
            'tenants' => $this->safeRoute('filament.admin.resources.tenants.index'),
            'users' => $this->safeRoute('filament.admin.resources.users.index'),
            'payments' => $this->safeRoute('filament.admin.resources.payments.index'),
            'settings' => $this->safeRoute('filament.admin.pages.manage-general-settings'),
            'billing' => $this->safeRoute('filament.admin.pages.billing'),
            'operations' => $this->safeRoute('filament.admin.resources.operational-playbook-tasks.index'),
        ];
    }

    private function safeRoute(string $name, array $parameters = []): string
    {
        try {
            return route($name, $parameters, false);
        } catch (\Throwable) {
            return '#';
        }
    }
}
