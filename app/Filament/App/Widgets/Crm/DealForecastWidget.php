<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Crm;

use App\Domain\Crm\Enums\DealStatus;
use App\Models\CrmDeal;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DealForecastWidget extends BaseWidget
{
    protected ?string $heading = 'Forecast de deals';

    protected static ?int $sort = 21;

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return [];
        }

        $openDeals = CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->where('status', DealStatus::Open->value)
            ->with(['stage' => fn ($query) => $query->where('tenant_id', $tenantId)])
            ->get();

        $openValue = (float) $openDeals->sum(fn (CrmDeal $deal): float => (float) $deal->value);
        $weightedValue = (float) $openDeals->sum(fn (CrmDeal $deal): float => $deal->weighted_value);

        $wonThisMonth = (float) CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->where('status', DealStatus::Won->value)
            ->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('value');

        return [
            Stat::make('Pipeline abierto', 'L '.number_format($openValue, 2))->color('info'),
            Stat::make('Valor ponderado', 'L '.number_format($weightedValue, 2))->color('primary'),
            Stat::make('Ganado este mes', 'L '.number_format($wonThisMonth, 2))->color('success'),
        ];
    }
}
