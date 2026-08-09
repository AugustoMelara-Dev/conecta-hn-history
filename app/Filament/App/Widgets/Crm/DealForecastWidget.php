<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Crm;

use App\Domain\Crm\Enums\DealStatus;
use App\Models\CrmDeal;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

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

        $openValue = (float) CrmDeal::where('tenant_id', $tenantId)
            ->where('status', DealStatus::Open->value)
            ->sum('value');

        $weightedValue = (float) DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->where('status', DealStatus::Open->value)
            ->selectRaw('SUM(value * probability / 100) as weighted')
            ->value('weighted');

        $wonThisMonth = (float) CrmDeal::where('tenant_id', $tenantId)
            ->where('status', DealStatus::Won->value)
            ->whereMonth('won_at', now()->month)
            ->sum('value');

        return [
            Stat::make('Pipeline abierto', 'L '.number_format($openValue, 2))->color('info'),
            Stat::make('Valor ponderado', 'L '.number_format($weightedValue, 2))->color('primary'),
            Stat::make('Ganado este mes', 'L '.number_format($wonThisMonth, 2))->color('success'),
        ];
    }
}
