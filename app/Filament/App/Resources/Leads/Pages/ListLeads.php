<?php

namespace App\Filament\App\Resources\Leads\Pages;

use App\Filament\App\Resources\Leads\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),
            'new' => Tab::make('Nuevos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'new'))
                ->badge(fn () => \App\Models\Lead::where('status', 'new')->where('tenant_id', \Filament\Facades\Filament::getTenant()->id)->count())
                ->badgeColor('danger'),
            'contacted' => Tab::make('En Proceso')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'contacted'))
                ->badgeColor('info'),
            'closed' => Tab::make('Cerrados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'closed'))
                ->badgeColor('success'),
            'lost' => Tab::make('Perdidos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'lost'))
                ->badgeColor('gray'),
        ];
    }
}
