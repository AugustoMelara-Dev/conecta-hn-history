<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * OpeningHoursRelationManager - Tenant Business Hours Configuration
 *
 * Manages the opening_hours table for the current tenant.
 * SECURITY: Automatically scoped via the Tenant relationship.
 */
class OpeningHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'openingHours';

    protected static ?string $title = 'Horario de Atención';

    protected static ?string $modelLabel = 'Horario';

    protected static ?string $pluralModelLabel = 'Horarios';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('day_of_week')
                    ->label('Día de la Semana')
                    ->options([
                        0 => 'Domingo',
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                    ])
                    ->required()
                    ->native(false),

                Forms\Components\TimePicker::make('opening_time')
                    ->label('Hora de Apertura')
                    ->seconds(false)
                    ->required()
                    ->native(false),

                Forms\Components\TimePicker::make('closing_time')
                    ->label('Hora de Cierre')
                    ->seconds(false)
                    ->required()
                    ->native(false)
                    ->after('opening_time'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('day_of_week')
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Día')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        0 => 'Domingo',
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                        default => 'Desconocido',
                    })
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 || $state === 6 ? 'warning' : 'primary'),

                Tables\Columns\TextColumn::make('opening_time')
                    ->label('Apertura')
                    ->time('h:i A')
                    ->icon('heroicon-o-sun'),

                Tables\Columns\TextColumn::make('closing_time')
                    ->label('Cierre')
                    ->time('h:i A')
                    ->icon('heroicon-o-moon'),
            ])
            ->defaultSort('day_of_week')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar Horario'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
