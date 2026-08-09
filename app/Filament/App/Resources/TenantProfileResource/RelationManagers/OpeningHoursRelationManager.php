<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OpeningHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'openingHours';

    protected static ?string $title = 'Horario de Atención';

    protected static ?string $modelLabel = 'Horario';

    protected static ?string $pluralModelLabel = 'horarios';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('day_of_week')
                    ->label('Dia de la semana')
                    ->options(self::days())
                    ->required()
                    ->native(false),

                Forms\Components\TimePicker::make('opening_time')
                    ->label('Hora de apertura')
                    ->seconds(false)
                    ->required()
                    ->native(false),

                Forms\Components\TimePicker::make('closing_time')
                    ->label('Hora de cierre')
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
            ->emptyStateHeading('Sin horarios configurados')
            ->emptyStateDescription('Agrega horarios para que tus clientes sepan cuando estas disponible.')
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Dia')
                    ->formatStateUsing(fn (int $state): string => self::days()[$state] ?? 'Desconocido')
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar horario'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function days(): array
    {
        return [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miercoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sabado',
        ];
    }
}
