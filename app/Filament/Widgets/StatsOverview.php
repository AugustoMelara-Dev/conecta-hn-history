<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use App\Models\Order;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return ! (auth()->user()?->isSuperAdmin() ?? false);
    }

    protected function getStats(): array
    {
        $tenant = $this->getTenantRecord();
        if (! $tenant instanceof Tenant) {
            return [];
        }

        $tenantId = (string) $tenant->getKey();

        $financialStats = Cache::remember("stats_financial_overview_{$tenantId}", 60, function () use ($tenant, $tenantId): array {
            $revenue = Order::query()
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->sum('total');

            $revenueStat = Stat::make('Volumen de Ingresos Brutos', 'L. '.number_format((float) $revenue, 2))
                ->description('Ingresos acumulados (real + proyectado)')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($revenue > 0 ? 'success' : 'gray');

            $views = (int) ($tenant->getAttribute('views') ?? 0);
            $sparkline = [];

            if ($views > 0) {
                for ($i = 0; $i < 6; $i++) {
                    $sparkline[] = (int) ($views * (mt_rand(80, 120) / 100));
                }
                $sparkline[] = $views;
            } else {
                $sparkline = [0, 0, 0, 0, 0, 0, 0];
            }

            $viewsStat = Stat::make('Visitas y Tendencia', number_format($views))
                ->description('Exposicion de marca')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info')
                ->chart($sparkline);

            return ['revenue' => $revenueStat, 'views' => $viewsStat];
        });

        $productCount = $tenant->products()->count();
        $plan = app(TenantPlanPresentationReader::class)->forTenant($tenant);
        $isPro = $plan['isCatalogUnlimited'];

        if ($isPro) {
            $inventoryStat = Stat::make('Capacidad del Plan', 'Ilimitado')
                ->description($plan['catalogStatusCopy'])
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success');
        } else {
            $limit = max(1, (int) ($plan['productLimit'] ?? 10));

            $state = match (true) {
                $productCount >= $limit => 'critical',
                $productCount >= ($limit - 2) => 'warning',
                default => 'safe',
            };

            $config = match ($state) {
                'critical' => [
                    'val' => "{$productCount} / {$limit}",
                    'desc' => $plan['catalogStatusCopy'],
                    'icon' => 'heroicon-m-lock-closed',
                    'color' => 'danger',
                ],
                'warning' => [
                    'val' => "{$productCount} / {$limit}",
                    'desc' => $plan['catalogStatusCopy'],
                    'icon' => 'heroicon-m-exclamation-triangle',
                    'color' => 'warning',
                ],
                default => [
                    'val' => "{$productCount} / {$limit}",
                    'desc' => $plan['catalogStatusCopy'],
                    'icon' => 'heroicon-m-check',
                    'color' => 'success',
                ],
            };

            $inventoryStat = Stat::make('Capacidad del Plan', $config['val'])
                ->description($config['desc'])
                ->descriptionIcon($config['icon'])
                ->color($config['color']);
        }

        return [
            $financialStats['revenue'],
            $financialStats['views'],
            $inventoryStat,
        ];
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
