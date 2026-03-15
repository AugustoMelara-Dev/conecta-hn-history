<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Reseñas';

    protected static ?string $modelLabel = 'Reseña';

    protected static ?string $pluralModelLabel = 'Reseñas';

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Toggle::make('is_approved')
                ->label('Aprobada para publicación')
                ->required(),
            Forms\Components\TextInput::make('rating')
                ->label('Calificación')
                ->disabled(),
            Forms\Components\Textarea::make('comment')
                ->label('Comentario')
                ->disabled()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->default('General')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Calificación')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Aprobada')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Estado')
                    ->boolean()
                    ->trueLabel('Aprobadas')
                    ->falseLabel('Pendientes')
                    ->placeholder('Todas'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Review $record): bool => ! $record->is_approved)
                    ->action(fn (Review $record) => $record->update(['is_approved' => true])),
                Tables\Actions\Action::make('reject')
                    ->label('Ocultar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Review $record): bool => $record->is_approved)
                    ->action(fn (Review $record) => $record->update(['is_approved' => false])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
        ];
    }
}