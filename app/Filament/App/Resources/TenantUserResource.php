<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\TenantUserResource\Pages;
use App\Models\TenantUser;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
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

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationLabel(): string
    {
        return 'Equipo';
    }

    public static function getModelLabel(): string
    {
        return 'miembro';
    }

    public static function getPluralModelLabel(): string
    {
        return 'equipo';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacion del miembro')
                    ->description('Elige la cuenta del usuario y define su nivel de acceso al negocio.')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuario')
                            ->relationship('user', 'name')
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->helperText('Busca por nombre o correo electronico.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Correo electronico')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email'),
                                Forms\Components\TextInput::make('password')
                                    ->label('Contrasena')
                                    ->password()
                                    ->required()
                                    ->minLength(8),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Crear usuario')
                                    ->modalDescription('Crea una cuenta para agregarla al equipo de este negocio.')
                                    ->modalWidth('md');
                            })
                            ->columnSpan(1)
                            ->disabled(fn (?TenantUser $record) => $record !== null),

                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options([
                                'owner' => 'Propietario',
                                'manager' => 'Gerente',
                                'employee' => 'Empleado',
                            ])
                            ->default('employee')
                            ->required()
                            ->helperText('Define el nivel de acceso general. Los permisos detallados aplican para gerente y empleado.')
                            ->live()
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Permisos de acceso')
                    ->icon('heroicon-o-shield-check')
                    ->description('Activa solo lo que esta persona necesita para hacer su trabajo.')
                    ->columns(2)
                    ->visible(fn (Forms\Get $get): bool => $get('role') !== 'owner')
                    ->schema([
                        Forms\Components\Toggle::make('can_manage_products')
                            ->label('Gestionar productos')
                            ->helperText('Crear, editar y eliminar productos.')
                            ->default(true),

                        Forms\Components\Toggle::make('can_manage_orders')
                            ->label('Gestionar pedidos')
                            ->helperText('Ver pedidos y actualizar sus estados.')
                            ->default(true),

                        Forms\Components\Toggle::make('can_view_analytics')
                            ->label('Ver estadisticas')
                            ->helperText('Acceso al dashboard y metricas del negocio.')
                            ->default(false),

                        Forms\Components\Toggle::make('can_manage_settings')
                            ->label('Editar configuracion')
                            ->helperText('Editar perfil, ajustes y datos operativos del negocio.')
                            ->default(false),

                        Forms\Components\Toggle::make('can_manage_team')
                            ->label('Gestionar equipo')
                            ->helperText('Invitar, editar y administrar miembros del equipo.')
                            ->default(false),

                        Forms\Components\Toggle::make('can_manage_billing')
                            ->label('Gestionar facturacion')
                            ->helperText('Acceso a pagos, plan y suscripcion.')
                            ->default(false),
                    ]),

                Forms\Components\Section::make('Informacion de acceso')
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn (?TenantUser $record) => $record !== null)
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('invited_at_display')
                            ->label('Invitado')
                            ->content(fn (?TenantUser $record): string => $record?->invited_at?->format('d/m/Y H:i') ?? 'No registrado'),

                        Forms\Components\Placeholder::make('accepted_at_display')
                            ->label('Aceptado')
                            ->content(fn (?TenantUser $record): string => $record?->accepted_at?->format('d/m/Y H:i') ?? 'Pendiente'),

                        Forms\Components\Placeholder::make('last_access_display')
                            ->label('Ultimo acceso')
                            ->content(fn (?TenantUser $record): string => $record?->last_access_at?->diffForHumans() ?? 'Nunca'),
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::roleLabel($state))
                    ->color(fn (mixed $state): string => match (self::roleValue($state)) {
                        'owner' => 'warning',
                        'manager' => 'info',
                        'employee' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Activo' : 'Pendiente')
                    ->color(fn ($state): string => $state ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('last_access_at')
                    ->label('Ultimo acceso')
                    ->since()
                    ->placeholder('Nunca'),

                Tables\Columns\TextColumn::make('permissions_summary')
                    ->label('Permisos')
                    ->state(fn (TenantUser $record): string => self::permissionsSummary($record))
                    ->tooltip('Productos, pedidos, estadisticas, configuracion, equipo y facturacion.'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        'owner' => 'Propietario',
                        'manager' => 'Gerente',
                        'employee' => 'Empleado',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->iconButton(),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->iconButton()
                    ->modalHeading('Eliminar miembro del equipo')
                    ->modalDescription('Esta accion quitara el acceso de esta persona al negocio. La cuenta de usuario no se elimina.')
                    ->modalSubmitActionLabel('Eliminar acceso')
                    ->visible(fn (TenantUser $record): bool => self::roleValue($record->role) !== 'owner'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar miembros seleccionados')
                        ->modalDescription('Esta accion quita el acceso de los miembros seleccionados. Las cuentas de usuario no se eliminan.')
                        ->modalSubmitActionLabel('Eliminar accesos'),
                ]),
            ])
            ->emptyStateHeading('Aun no hay miembros del equipo')
            ->emptyStateDescription('Agrega empleados o gerentes para delegar la operacion del negocio con permisos controlados.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar miembro'),
            ]);
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

    private static function roleValue(mixed $role): string
    {
        if ($role instanceof BackedEnum) {
            return (string) $role->value;
        }

        return (string) $role;
    }

    private static function roleLabel(mixed $role): string
    {
        return match (self::roleValue($role)) {
            'owner' => 'Propietario',
            'manager' => 'Gerente',
            'employee' => 'Empleado',
            default => 'Miembro',
        };
    }

    private static function permissionsSummary(TenantUser $record): string
    {
        if (self::roleValue($record->role) === 'owner') {
            return 'Acceso total';
        }

        $permissions = [];

        if ($record->can_manage_products) {
            $permissions[] = 'Productos';
        }

        if ($record->can_manage_orders) {
            $permissions[] = 'Pedidos';
        }

        if ($record->can_view_analytics) {
            $permissions[] = 'Estadisticas';
        }

        if ($record->can_manage_settings) {
            $permissions[] = 'Configuracion';
        }

        if ($record->can_manage_team) {
            $permissions[] = 'Equipo';
        }

        if ($record->can_manage_billing) {
            $permissions[] = 'Facturacion';
        }

        return $permissions === [] ? 'Sin permisos activos' : implode(', ', $permissions);
    }
}
