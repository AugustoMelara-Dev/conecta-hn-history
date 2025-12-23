<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
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
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(Order::query()->count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Pendientes')
                ->badge(Order::query()->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),

            'processing' => Tab::make('En Preparación')
                ->badge(Order::query()->where('status', 'processing')->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing')),

            'ready' => Tab::make('Listos')
                ->badge(Order::query()->where('status', 'ready')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'ready')),

            'delivered' => Tab::make('Entregados')
                ->badge(Order::query()->where('status', 'delivered')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'delivered')),
        ];
    }
}
