<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class TenantAiAgentWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.tenant-ai-agent-widget';

    protected static bool $isLazy = false;

    protected static ?int $sort = -8;

    public bool $isPro = false;

    public bool $isAllowlisted = false;

    public bool $hasPermission = false;

    public bool $canUseAgent = false;

    public ?string $tenantKey = null;

    public ?string $tenantSlug = null;

    public string $upgradeUrl = '';

    public string $agentEndpoint = '';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        $this->upgradeUrl = url('/app');
        $this->agentEndpoint = route('api.tenant.agent.chat');

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->tenantKey = (string) $tenant->getKey();
        $this->tenantSlug = (string) ($tenant->slug ?? '');
        $this->upgradeUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
        $this->isPro = $this->tenantIsPro($tenant);
        $this->isAllowlisted = $this->tenantIsAllowlisted($tenant);

        if ($user instanceof User) {
            $this->hasPermission = app(TenantPermissionGuardInterface::class)->allows(
                $user,
                $tenant,
                Capability::AI_CONTENT,
                Permission::AI_USE,
            );
        }

        $this->canUseAgent = $this->isPro && $this->isAllowlisted && $this->hasPermission;
    }

    public function getStatusBadgeColor(): string
    {
        return $this->canUseAgent ? 'success' : 'warning';
    }

    public function getStatusLabel(): string
    {
        if ($this->canUseAgent) {
            return 'Activado por tenant';
        }

        if (! $this->isPro) {
            return 'Requiere PRO';
        }

        if (! $this->isAllowlisted) {
            return 'Activacion segura pendiente';
        }

        return 'Permiso ai.use pendiente';
    }

    public function getBlockerCopy(): string
    {
        if (! $this->isPro) {
            return 'El agente privado es valor PRO: ventas, reservas, catalogo y metricas del negocio sin exponer datos de otros tenants.';
        }

        if (! $this->isAllowlisted) {
            return 'Tu plan puede aplicar, pero la IA privada sigue apagada hasta una activacion segura por tenant.';
        }

        if (! $this->hasPermission) {
            return 'Este usuario necesita el permiso ai.use antes de consultar datos privados del negocio.';
        }

        return 'Listo para consultas seguras filtradas por tenant.';
    }

    private function tenantIsPro(Tenant $tenant): bool
    {
        $planType = (string) ($tenant->getRawOriginal('plan_type') ?? $tenant->plan_type ?? '');
        $status = (string) ($tenant->getRawOriginal('subscription_status') ?? $tenant->subscription_status ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS);

        if ($planType !== '') {
            return in_array($planType, ['pro', 'enterprise'], true)
                && in_array($status, ['active', 'trial'], true);
        }

        return (bool) ($tenant->is_pro ?? false);
    }

    private function tenantIsAllowlisted(Tenant $tenant): bool
    {
        $allowlist = config('conecta.features.tenant_private_agent_tenants', []);

        if (! is_array($allowlist) || $allowlist === []) {
            return false;
        }

        $values = array_map('strval', $allowlist);

        return in_array((string) $tenant->getKey(), $values, true)
            || in_array((string) ($tenant->slug ?? ''), $values, true);
    }
}
