<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\FollowerResource\Pages;
use App\Models\Follow;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FollowerResource extends Resource
{
    protected static ?string $model = Follow::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return 'Seguidores';
    }

    public static function getModelLabel(): string
    {
        return 'Seguidor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Seguidores';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Read-only view
                Forms\Components\TextInput::make('user.name')
                    ->label('Nombre'),
                Forms\Components\TextInput::make('user.email')
                    ->label('Email'),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Fecha de seguimiento'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isPro = Filament::getTenant()->is_pro;

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($isPro) {
                // If PRO, show followers. If Free, show empty query
                if ($isPro) {
                    return $query->where('tenant_id', Filament::getTenant()->id);
                }

                return $query->whereRaw('1 = 0');
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Desde')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->emptyStateHeading(
                $isPro
                    ? 'Aún no tienes seguidores'
                    : 'Descubre quién te sigue con PRO'
            )
            ->emptyStateDescription(
                $isPro
                    ? 'Comparte tu perfil para conseguir seguidores.'
                    : 'Actualiza a nuestro plan PRO para ver la lista completa de tus seguidores y contactarlos.'
            )
            ->emptyStateIcon($isPro ? 'heroicon-o-users' : 'heroicon-o-lock-closed')
            ->emptyStateActions(
                $isPro
                    ? []
                    : [
                        Action::make('upgrade')
                            ->label('Actualizar a PRO')
                            ->url(fn () => route('filament.app.pages.upgrade-to-pro', ['tenant' => Filament::getTenant()->slug]))
                            ->icon('heroicon-o-star')
                            ->button(),
                    ]
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFollowers::route('/'),
        ];
    }
}
