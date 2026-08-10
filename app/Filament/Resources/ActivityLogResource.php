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

/**
 * ActivityLogResource - La "Caja Negra" del Sistema
 *
 * Registro forense inmutable de todas las operaciones críticas.
 * Solo usuarios con rol 'super_admin' o 'auditor' pueden acceder.
 */
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
        return (string) static::getModel()::whereDate('created_at', today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Control de acceso: Solo super_admin y auditor
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        $superAdmins = config('auth.super_admins', []);
        $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);

        return $user && ($user->hasRole(['super_admin', 'auditor']) || $isWhitelisted);
    }

    /**
     * INMUTABLE: Nadie puede crear, editar o eliminar registros
     */
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
            ->components([
                Section::make('Detalles del Evento')
                    ->schema([
                        // Solo lectura
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Tipo de Evento
                // ═══════════════════════════════════════════════════════════
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

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Modelo Afectado
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Entidad')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID')
                    ->sortable(),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Descripcion del Cambio
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripcion')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: El Causante (Quien ejecuto la accion)
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Ejecutado por')
                    ->default('Sistema')
                    ->icon('heroicon-o-user')
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Direccion IP
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('properties.ip')
                    ->label('IP')
                    ->default('N/A')
                    ->badge()
                    ->color('gray'),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Fecha y Hora
                // ═══════════════════════════════════════════════════════════
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
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde'),
                        DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn ($q) => $q->whereDate('created_at', '>=', $data['desde']))
                            ->when($data['hasta'], fn ($q) => $q->whereDate('created_at', '<=', $data['hasta']));
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading('Detalles del Registro')
                    ->modalContent(fn (Activity $record) => view('filament.resources.activity-log.view', ['record' => $record])),
            ])
            ->bulkActions([
                // INMUTABLE: Sin acciones masivas
            ])
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
