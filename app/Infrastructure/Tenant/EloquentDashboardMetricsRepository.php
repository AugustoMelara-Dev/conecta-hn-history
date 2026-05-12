<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Contracts\DashboardMetricsRepository;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * EloquentDashboardMetricsRepository - Logic Concentration
 */
final class EloquentDashboardMetricsRepository implements DashboardMetricsRepository
{
    public function getKpiSummary(int|string $tenantId): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $tenant = Tenant::find($tenantId);
        $commercialMode = $this->commercialMode($tenant);

        $todayRevenue = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $today)
            ->whereIn('status', ['completed', 'paid'])
            ->sum('total');

        $yesterdayRevenue = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $yesterday)
            ->whereIn('status', ['completed', 'paid'])
            ->sum('total');

        $revenueTrend = $yesterdayRevenue > 0
            ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100
            : 0;

        return [
            'today_revenue' => (float) $todayRevenue,
            'today_orders' => Order::where('tenant_id', $tenantId)->whereDate('created_at', $today)->count(),
            'pending_orders' => Order::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'revenue_trend' => round($revenueTrend, 1),
            'commercial_mode' => $commercialMode,
            'commercial_mode_label' => $this->commercialModeLabel($commercialMode),
            'commercial_mode_kpis' => $this->getCommercialModeKpis($tenantId),
        ];
    }

    public function getCommercialModeKpis(int|string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        $mode = $this->commercialMode($tenant);

        $ordersToday = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $completedOrders = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['completed', 'paid'])
            ->count();

        $appointmentsToday = $this->hasModelTable('appointments')
            ? Appointment::query()->where('tenant_id', $tenantId)->whereDate('scheduled_at', Carbon::today())->count()
            : 0;

        $appointmentsConfirmed = $this->hasModelTable('appointments')
            ? Appointment::query()->where('tenant_id', $tenantId)->whereIn('status', ['confirmed', 'completed'])->count()
            : 0;

        $leadsCaptured = $this->hasModelTable('leads')
            ? Lead::query()->where('tenant_id', $tenantId)->count()
            : 0;

        $leadsQualified = $this->hasModelTable('leads')
            ? Lead::query()->where('tenant_id', $tenantId)->whereIn('status', ['qualified', 'won'])->count()
            : 0;

        return match ($mode) {
            'appointments' => [
                'headline' => 'Reservas confirmadas',
                'primary_value' => $appointmentsConfirmed,
                'secondary_value' => $appointmentsToday,
                'primary_label' => 'Reservas confirmadas',
                'secondary_label' => 'Reservas hoy',
                'activation_ready' => $appointmentsConfirmed > 0,
            ],
            'consulting_leads' => [
                'headline' => 'Consultas calificadas',
                'primary_value' => $leadsQualified,
                'secondary_value' => $leadsCaptured,
                'primary_label' => 'Consultas calificadas',
                'secondary_label' => 'Consultas captadas',
                'activation_ready' => $leadsCaptured > 0,
            ],
            default => [
                'headline' => 'Pedidos completados',
                'primary_value' => $completedOrders,
                'secondary_value' => $ordersToday,
                'primary_label' => 'Pedidos completados',
                'secondary_label' => 'Pedidos hoy',
                'activation_ready' => $completedOrders > 0,
            ],
        };
    }

    public function getRevenueTrend(int|string $tenantId, string $period = 'month'): array
    {
        return [
            'labels' => [],
            'datasets' => [],
        ];
    }

    public function getOnboardingStatus(int|string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return ['progress' => 0, 'steps' => []];
        }

        $steps = $this->onboardingStepsByMode($tenant);

        $completed = collect($steps)->where('completed', true)->count();
        $total = count($steps);
        $progress = $total > 0 ? round(($completed / $total) * 100) : 100;

        return ['progress' => $progress, 'steps' => $steps];
    }

    public function getLatestOrders(int|string $tenantId, int $limit = 5): array
    {
        return Order::where('tenant_id', $tenantId)
            ->with(['items'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'customer_name' => $order->customer_name,
                'total' => (float) $order->total,
                'status' => $order->status,
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    private function onboardingStepsByMode(Tenant $tenant): array
    {
        $mode = $this->commercialMode($tenant);

        $baseSteps = [
            ['id' => 'logo', 'title' => 'Subir logo', 'completed' => (bool) $tenant->logo_path, 'href' => $this->safeRoute('tenant.settings.index')],
            ['id' => 'phone', 'title' => 'Activar contacto', 'completed' => (bool) $tenant->phone || (bool) $tenant->whatsapp_number, 'href' => $this->safeRoute('tenant.settings.index')],
        ];

        if ($mode === 'appointments') {
            return [
                ...$baseSteps,
                ['id' => 'service', 'title' => 'Publicar servicio', 'completed' => $tenant->services()->exists(), 'href' => $this->safeRoute('tenant.services.index')],
                ['id' => 'schedule', 'title' => 'Definir disponibilidad', 'completed' => $tenant->openingHours()->exists() || ! empty($tenant->schedule), 'href' => $this->safeRoute('tenant.settings.index')],
            ];
        }

        if ($mode === 'consulting_leads') {
            return [
                ...$baseSteps,
                ['id' => 'landing', 'title' => 'Definir propuesta', 'completed' => (bool) $tenant->description || (bool) $tenant->about, 'href' => $this->safeRoute('tenant.settings.index')],
                ['id' => 'lead_channel', 'title' => 'Activar canal de consulta', 'completed' => (bool) $tenant->whatsapp_number || (bool) $tenant->phone, 'href' => $this->safeRoute('tenant.settings.index')],
            ];
        }

        return [
            ...$baseSteps,
            ['id' => 'product', 'title' => 'Agregar producto', 'completed' => $tenant->products()->exists(), 'href' => $this->safeRoute('tenant.products.create')],
            ['id' => 'offer', 'title' => 'Definir oferta', 'completed' => (bool) $tenant->description, 'href' => $this->safeRoute('tenant.settings.index')],
        ];
    }

    private function safeRoute(string $name): string
    {
        try {
            return route($name);
        } catch (\Throwable) {
            return '#';
        }
    }

    private function hasModelTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function commercialMode(?Tenant $tenant): string
    {
        if (! $tenant instanceof Tenant) {
            return 'products';
        }

        $raw = $tenant->commercial_mode;
        if ($raw instanceof \BackedEnum) {
            return (string) $raw->value;
        }

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        $legacy = $tenant->business_type;
        if ($legacy instanceof \BackedEnum) {
            return $legacy->value === 'service' ? 'appointments' : 'products';
        }

        return $legacy === 'service' ? 'appointments' : 'products';
    }

    private function commercialModeLabel(string $mode): string
    {
        return match ($mode) {
            'appointments' => 'Starter Appointments',
            'consulting_leads' => 'Starter Consulting',
            'hybrid' => 'Operacion comercial mixta',
            default => 'Starter Products',
        };
    }
}
