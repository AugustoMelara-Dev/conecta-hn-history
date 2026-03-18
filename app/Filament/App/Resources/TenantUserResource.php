<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\TenantUserResource\Pages;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TenantUserResource extends Resource
{
    protected static ?string $model = TenantMembership::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', TenantMembership::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
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
        return 'Configuracion';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Informacion del Miembro')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuario')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (User $record): string => "{$record->name} ({$record->email})"
                            )
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->helperText('Selecciona un usuario existente o crea uno nuevo para este equipo.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('users', 'email'),
                                Forms\Components\TextInput::make('password')
                                    ->label('Contrasena temporal')
                                    ->password()
                                    ->required()
                                    ->minLength(8)
                                    ->helperText('Se almacenara de forma segura y el usuario podra cambiarla luego.'),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $passwordHasher = app(PasswordHasherInterface::class);

                                $user = User::query()->create([
                                    'name' => $data['name'],
                                    'email' => Str::lower(trim($data['email'])),
                                    'password' => $passwordHasher->hash($data['password']),
                                    'role' => UserRole::STAFF->value,
                                ]);

                                return (string) $user->getKey();
                            })
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Crear Nuevo Usuario')
                                    ->modalWidth('md');
                            })
                            ->disabled(fn (?TenantMembership $record) => $record !== null)
                            ->columnSpan(1),

                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options(static::getRoleOptions())
                            ->default(Role::OPERATOR->value)
                            ->required()
                            ->native(false)
                            ->helperText(static::getRoleHelperText())
                            ->disabled(fn (?TenantMembership $record) => $record?->isOwner() ?? false)
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Informacion de Acceso')
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn (?TenantMembership $record) => $record !== null)
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('user_email_display')
                            ->label('Email')
                            ->content(fn (?TenantMembership $record): string => $record?->user?->email ?? 'N/A'),

                        Forms\Components\Placeholder::make('assigned_at_display')
                            ->label('Asignado')
                            ->content(
                                fn (?TenantMembership $record): string => $record?->created_at?->format('d/m/Y H:i') ?? 'N/A'
                            ),

                        Forms\Components\Placeholder::make('accepted_at_display')
                            ->label('Estado')
                            ->content(
                                fn (?TenantMembership $record): string => $record?->accepted_at
                                    ? 'Activo desde '.$record->accepted_at->format('d/m/Y H:i')
                                    : 'Pendiente'
                            ),

                        Forms\Components\Placeholder::make('preset_guidance')
                            ->label('Presets disponibles')
                            ->content(static::getActivePresetSummary()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $tenant = Filament::getTenant();

                if (! $tenant) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->where('tenant_id', $tenant->id)
                    ->with('user');
            })
            ->columns([
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

                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->state(fn (TenantMembership $record): string => static::getRoleLabel($record->role))
                    ->color(fn (TenantMembership $record): string => static::getRoleColor($record->role)),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (TenantMembership $record): string => $record->accepted_at ? 'Activo' : 'Pendiente')
                    ->color(fn (TenantMembership $record): string => $record->accepted_at ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Asignado')
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options(static::getRoleOptions()),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (TenantMembership $record): bool => ! $record->isOwner()),
            ])
            ->emptyStateHeading('Sin miembros del equipo')
            ->emptyStateDescription('Agrega personas y asignales un rol claro dentro del negocio.')
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

    private static function getRoleOptions(): array
    {
        return collect(array_keys(config('rbac.roles', [])))
            ->filter(static fn (mixed $value): bool => is_string($value) && Role::tryFrom($value) !== null)
            ->mapWithKeys(static fn (string $value): array => [$value => static::getRoleLabel($value)])
            ->all();
    }

    private static function getRoleLabel(Role|string|null $role): string
    {
        $value = $role instanceof Role ? $role->value : (string) $role;

        return match ($value) {
            Role::OWNER->value => 'Dueno',
            Role::ADMIN->value => 'Admin',
            Role::MANAGER->value => 'Manager',
            Role::OPERATOR->value => 'Operador',
            Role::VIEWER->value => 'Solo lectura',
            default => Str::of($value)->replace('_', ' ')->title()->value(),
        };
    }

    private static function getRoleColor(Role|string|null $role): string
    {
        $value = $role instanceof Role ? $role->value : (string) $role;

        return match ($value) {
            Role::OWNER->value => 'warning',
            Role::ADMIN->value => 'info',
            Role::MANAGER->value => 'info',
            Role::OPERATOR->value => 'success',
            Role::VIEWER->value => 'gray',
            default => 'gray',
        };
    }

    private static function getRoleHelperText(): string
    {
        $summary = static::getActivePresetSummary();

        if ($summary === 'Sin presets adicionales para este negocio.') {
            return 'Asigna uno de los roles base. Los presets verticales se apoyaran sobre estos mismos cimientos.';
        }

        return $summary;
    }

    private static function getActivePresetSummary(): string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return 'Sin contexto de tenant.';
        }

        $presets = collect(config('rbac.presets', []))
            ->filter(function (array $preset) use ($tenant): bool {
                $requiredCapability = $preset['requires_capability'] ?? null;

                if (! is_string($requiredCapability)) {
                    return false;
                }

                $capability = Capability::tryFrom($requiredCapability);

                return $capability !== null && $tenant->hasCapability($capability);
            })
            ->map(function (array $preset, string $key): string {
                $role = Role::tryFrom((string) ($preset['role'] ?? ''));
                $roleLabel = $role ? static::getRoleLabel($role) : 'Rol base';

                return Str::of($key)->replace('_', ' ')->title()->value().' -> '.$roleLabel;
            })
            ->values()
            ->all();

        if ($presets === []) {
            return 'Sin presets adicionales para este negocio.';
        }

        return 'Sugerencias para este negocio: '.implode(', ', $presets).'.';
    }
}
