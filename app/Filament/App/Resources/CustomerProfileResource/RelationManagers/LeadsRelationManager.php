<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\RelationManagers;

use App\Filament\App\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Historial de prospectos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'whatsapp_click' => 'WhatsApp',
                        'catalog' => 'Catalogo',
                        'website' => 'Sitio web',
                        'manual' => 'Manual',
                        null, '' => 'Manual',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'Nuevo',
                        'contacted' => 'Contactado',
                        'closed' => 'Cerrado',
                        'lost' => 'Perdido',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (Lead $record): string {
                        $tenant = Filament::getTenant();

                        return $tenant instanceof Tenant
                            ? LeadResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $record])
                            : '#';
                    }),
            ])
            ->bulkActions([]);
    }
}
