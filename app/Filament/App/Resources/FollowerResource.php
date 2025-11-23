<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\FollowerResource\Pages;
use App\Models\Follow;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

class FollowerResource extends Resource
{
    protected static ?string $model = Follow::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'Seguidores';
    
    protected static ?string $modelLabel = 'Seguidor';
    
    protected static ?string $pluralModelLabel = 'Seguidores';

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
            ->query(
                // If PRO, show followers. If Free, show empty query (or we could handle this via view)
                // But the requirement says "The table shows a placeholder message with upgrade CTA"
                // We can achieve this by returning an empty query for Free users and using emptyState
                fn (Builder $query) => $isPro 
                    ? $query->where('tenant_id', Filament::getTenant()->id)
                    : $query->whereRaw('1 = 0')
            )
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
                        Tables\Actions\Action::make('upgrade')
                            ->label('Actualizar a PRO')
                            ->url('#') // In a real app, this would go to billing
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
