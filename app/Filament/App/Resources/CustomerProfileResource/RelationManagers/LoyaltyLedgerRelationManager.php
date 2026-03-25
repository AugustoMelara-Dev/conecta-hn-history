<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\RelationManagers;

use App\Models\CustomerLoyaltyLedger;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyLedgerRelationManager extends RelationManager
{
    protected static string $relationship = 'loyaltyLedger';

    protected static ?string $title = 'Fidelidad';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('awarded_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('type_label')
                    ->label('Movimiento')
                    ->badge()
                    ->color(fn (CustomerLoyaltyLedger $record): string => match ($record->type) {
                        CustomerLoyaltyLedger::TYPE_EARN => 'success',
                        CustomerLoyaltyLedger::TYPE_REDEEM => 'warning',
                        CustomerLoyaltyLedger::TYPE_BONUS => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('points_delta')
                    ->label('Puntos')
                    ->badge()
                    ->color(fn (CustomerLoyaltyLedger $record): string => $record->points_delta >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Detalle')
                    ->wrap()
                    ->limit(100),
                Tables\Columns\TextColumn::make('reward.name')
                    ->label('Recompensa')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Responsable')
                    ->default('Sistema')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('awarded_at')
                    ->label('Fecha')
                    ->since(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
