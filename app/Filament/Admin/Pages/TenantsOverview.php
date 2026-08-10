<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TenantsOverview - Top-level tenant dashboard.
 */
class TenantsOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?string $navigationLabel = 'Tenants Overview';

    protected static ?string $title = 'Tenants Overview';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.admin.pages.tenants-overview';

    public function getTopTenants(): array
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('tenants')) {
            return [];
        }

        return DB::table('orders')
            ->selectRaw('tenant_id, COUNT(*) as orders_count, SUM(total) as revenue')
            ->where('payment_status', 'paid')
            ->groupBy('tenant_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $tenant = DB::table('tenants')->where('id', $row->tenant_id)->first();

                return [
                    'name' => $tenant->name ?? 'Unknown',
                    'orders_count' => (int) $row->orders_count,
                    'revenue' => (float) $row->revenue,
                ];
            })->toArray();
    }
}
