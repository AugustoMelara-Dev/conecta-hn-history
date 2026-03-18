<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function getNavigationLabel(): string
    {
        return 'Bitacora de Actividad';
    }

    public static function getModelLabel(): string
    {
        return 'Registro de Actividad';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Registros de Actividad';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Seguridad';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()->whereDate('created_at', today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $superAdmins = config('auth.super_admins', []);
        $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);
        $rawRole = (string) $user->getRawOriginal('role');

        return $user->isSuperAdmin() || $isWhitelisted || $rawRole === 'auditor';
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Detalles del Evento')->schema([]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Creado',
                        'updated' => 'Modificado',
                        'deleted' => 'Eliminado',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Entidad')
                    ->formatStateUsing(fn (?string $state): string => class_basename((string) $state))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripcion')
                    ->limit(50)
                    ->tooltip(fn (Activity $record): string => (string) $record->description),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Ejecutado por')
                    ->default('Sistema')
                    ->icon('heroicon-o-user')
                    ->searchable(),
                Tables\Columns\TextColumn::make('causer.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('properties.ip')
                    ->label('IP')
                    ->default('N/A')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Tipo de Evento')
                    ->options([
                        'created' => 'Creado',
                        'updated' => 'Modificado',
                        'deleted' => 'Eliminado',
                    ]),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Entidad')
                    ->options([
                        'App\\Models\\Tenant' => 'Negocio',
                        'App\\Models\\Payment' => 'Pago',
                        'App\\Models\\User' => 'Usuario',
                        'App\\Models\\Category' => 'Categoria',
                        'App\\Models\\City' => 'Ciudad',
                    ]),
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Usuario')
                    ->options(fn (): array => User::query()->pluck('name', 'id')->toArray())
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'] ?? null, fn ($q, $desde) => $q->whereDate('created_at', '>=', $desde))
                            ->when($data['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('created_at', '<=', $hasta));
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading('Detalles del Registro')
                    ->modalContent(fn (Activity $record) => view('filament.resources.activity-log.view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
