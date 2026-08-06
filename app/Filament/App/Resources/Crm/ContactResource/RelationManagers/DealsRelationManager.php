<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ContactResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DealsRelationManager extends RelationManager
{
    protected static string $relationship = 'deals';

    protected static ?string $title = 'Deals';

    protected static ?string $icon = 'heroicon-o-currency-dollar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del deal')
                    ->required(),
                Select::make('pipeline_id')
                    ->label('Pipeline')
                    ->relationship('pipeline', 'name')
                    ->required(),
                Select::make('stage_id')
                    ->label('Etapa')
                    ->relationship('stage', 'name'),
                TextInput::make('value')
                    ->label('Valor')
                    ->numeric()
                    ->prefix('L'),
                DateTimePicker::make('expected_close_date')
                    ->label('Fecha esperada de cierre'),
                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Deal'),
                TextColumn::make('value')
                    ->label('Valor')
                    ->money('HNL'),
                TextColumn::make('stage.name')
                    ->label('Etapa'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}



