<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\FollowerResource\Pages;
use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use App\Models\Follow;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FollowerResource extends Resource
{
    protected static ?string $model = Follow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

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

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
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
        $tenant = static::getTenantRecord();
        $plan = static::getPlanPresentation();
        $hasFollowerInsights = (bool) ($plan['hasFollowerInsights'] ?? false);

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($hasFollowerInsights, $tenant) {
                if ($hasFollowerInsights) {
                    return $query->where('tenant_id', (string) $tenant?->getKey());
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
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => $hasFollowerInsights ? 'Audiencia' : ($plan['marketingLabel'] ?? 'Plan Base'),
                'heading' => $hasFollowerInsights
                    ? 'Todavía no tienes seguidores registrados'
                    : 'La lista de seguidores se desbloquea con PRO',
                'description' => $hasFollowerInsights
                    ? 'Cuando alguien siga tu negocio, su registro aparecerá aquí para que puedas entender mejor tu audiencia.'
                    : ($plan['followersCopy'] ?? 'En el plan Base mantienes el conteo general.'),
            ]));
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPlanPresentation(): array
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return app(TenantPlanPresentationReader::class)->forTenant($tenant);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFollowers::route('/'),
        ];
    }
}
