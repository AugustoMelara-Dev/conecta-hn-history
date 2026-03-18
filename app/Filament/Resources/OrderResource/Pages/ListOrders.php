<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Kanban-style tabs for quick filtering by status
     *
     * SECURITY: All badge queries explicitly filter by tenant_id
     * to prevent cross-tenant data exposure (VC-AUDIT-002 fix).
     */
    public function getTabs(): array
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Tenant) {
            return [];
        }

        $tenantId = (string) $tenant->getKey();

        return [
            'all' => Tab::make('Todos')
                ->badge(Order::query()->where('tenant_id', $tenantId)->count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Pendientes')
                ->badge(Order::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),

            'processing' => Tab::make('En Preparación')
                ->badge(Order::query()->where('tenant_id', $tenantId)->where('status', 'processing')->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing')),

            'ready' => Tab::make('Listos')
                ->badge(Order::query()->where('tenant_id', $tenantId)->where('status', 'ready')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'ready')),

            'delivered' => Tab::make('Entregados')
                ->badge(Order::query()->where('tenant_id', $tenantId)->where('status', 'delivered')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'delivered')),
        ];
    }
}
