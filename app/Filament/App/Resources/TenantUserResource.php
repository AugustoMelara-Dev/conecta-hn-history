<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\TenantUserResource\Pages;
use App\Models\TenantUser;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantUserResource extends Resource
{
    protected static ?string $model = TenantUser::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationLabel(): string
    {
        return 'Equipo';
    }

    public static function getModelLabel(): string
    {
        return 'Miembro';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Equipo';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuración';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // ═══ SECCIÓN: SELECCIÓN DE USUARIO ═══
                Forms\Components\Section::make('Información del Miembro')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuario')
                            ->relationship('user', 'name')
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->helperText('Busca por nombre o email')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email'),
                                Forms\Components\TextInput::make('password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->required()
                                    ->minLength(8),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Crear Nuevo Usuario')
                                    ->modalWidth('md');
                            })
                            ->columnSpan(1)
                            ->disabled(fn (?TenantUser $record) => $record !== null),

                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options([
                                'owner' => '👑 Dueño',
                                'manager' => '👔 Gerente',
                                'employee' => '👤 Empleado',
                            ])
                            ->default('employee')
                            ->required()
                            ->helperText('Define el nivel de acceso')
                            ->live()
                            ->columnSpan(1),
                    ]),

                // ═══ SECCIÓN: PERMISOS GRANULARES ═══
                Forms\Components\Section::make('Permisos de Acceso')
                    ->icon('heroicon-o-shield-check')
                    ->description('Configura qué puede hacer este miembro')
                    ->columns(2)
                    ->visible(fn (Forms\Get $get): bool => $get('role') !== 'owner')
                    ->schema([
                        Forms\Components\Toggle::make('can_manage_products')
                            ->label('Gestionar Productos')
                            ->helperText('Crear, editar y eliminar productos')
                            ->default(true),

                        Forms\Components\Toggle::make('can_manage_orders')
                            ->label('Gestionar Pedidos')
                            ->helperText('Ver y actualizar estado de pedidos')
                            ->default(true),

                        Forms\Components\Toggle::make('can_view_analytics')
                            ->label('Ver Estadísticas')
                            ->helperText('Acceso al dashboard de métricas')
                            ->default(false),

                        Forms\Components\Toggle::make('can_manage_settings')
                            ->label('Configuración')
                            ->helperText('Editar perfil del negocio')
                            ->default(false),

                        Forms\Components\Toggle::make('can_manage_team')
                            ->label('Gestionar Equipo')
                            ->helperText('Invitar y administrar miembros')
                            ->default(false),

                        Forms\Components\Toggle::make('can_manage_billing')
                            ->label('Facturación')
                            ->helperText('Acceso a pagos y suscripción')
                            ->default(false),
                    ]),

                // ═══ INFORMACIÓN ADICIONAL (Solo ver) ═══
                Forms\Components\Section::make('Información de Acceso')
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn (?TenantUser $record) => $record !== null)
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('invited_at_display')
                            ->label('Invitado')
                            ->content(fn (?TenantUser $record): string => $record?->invited_at?->format('d/m/Y H:i') ?? 'N/A'
                            ),

                        Forms\Components\Placeholder::make('accepted_at_display')
                            ->label('Aceptado')
                            ->content(fn (?TenantUser $record): string => $record?->accepted_at?->format('d/m/Y H:i') ?? 'Pendiente'
                            ),

                        Forms\Components\Placeholder::make('last_access_display')
                            ->label('Último Acceso')
                            ->content(fn (?TenantUser $record): string => $record?->last_access_at?->diffForHumans() ?? 'Nunca'
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('tenant_id', Filament::getTenant()->id)
                ->with('user'))
            ->columns([
                // ═══ USUARIO ═══
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                // ═══ ROL CON COLORES ═══
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner' => '👑 Dueño',
                        'manager' => '👔 Gerente',
                        'employee' => '👤 Empleado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'warning',
                        'manager' => 'info',
                        'employee' => 'gray',
                        default => 'gray',
                    }),

                // ═══ ESTADO ═══
                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? '✓ Activo' : '⏳ Pendiente')
                    ->color(fn ($state): string => $state ? 'success' : 'warning'),

                // ═══ ÚLTIMO ACCESO ═══
                Tables\Columns\TextColumn::make('last_access_at')
                    ->label('Último Acceso')
                    ->since()
                    ->placeholder('Nunca'),

                // ═══ PERMISOS RESUMIDOS ═══
                Tables\Columns\TextColumn::make('permissions_summary')
                    ->label('Permisos')
                    ->state(function (TenantUser $record): string {
                        $perms = [];
                        if ($record->can_manage_products) {
                            $perms[] = '📦';
                        }
                        if ($record->can_manage_orders) {
                            $perms[] = '📋';
                        }
                        if ($record->can_view_analytics) {
                            $perms[] = '📊';
                        }
                        if ($record->can_manage_settings) {
                            $perms[] = '⚙️';
                        }

                        return implode(' ', $perms) ?: '—';
                    })
                    ->tooltip('📦=Productos, 📋=Pedidos, 📊=Analytics, ⚙️=Config'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        'owner' => 'Dueño',
                        'manager' => 'Gerente',
                        'employee' => 'Empleado',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (TenantUser $record): bool => $record->role !== 'owner'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin miembros del equipo')
            ->emptyStateDescription('Agrega empleados o gerentes para delegar la gestión de tu negocio.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantUsers::route('/'),
            'create' => Pages\CreateTenantUser::route('/create'),
            'edit' => Pages\EditTenantUser::route('/{record}/edit'),
        ];
    }
}
