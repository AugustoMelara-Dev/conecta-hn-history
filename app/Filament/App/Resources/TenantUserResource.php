<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\TenantUserResource\Pages;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
                Section::make('Informacion del Miembro')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuario')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (User $record): string => "{$record->name} ({$record->email})"
                            )
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->helperText('Selecciona un usuario existente o crea uno nuevo.')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('users', 'email'),
                                TextInput::make('password')
                                    ->label('Contrasena temporal')
                                    ->password()
                                    ->required()
                                    ->minLength(8)
                                    ->helperText('Se almacenara de forma segura y el usuario podra cambiarla luego.'),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $passwordHasher = app(PasswordHasherInterface::class);

                                $user = User::query()->create([
                                    'name' => (string) $data['name'],
                                    'email' => Str::lower(trim((string) $data['email'])),
                                    'password' => $passwordHasher->hash((string) $data['password']),
                                    'role' => UserRole::STAFF->value,
                                ]);

                                return (string) $user->getKey();
                            })
                            ->createOptionAction(function (Action $action): Action {
                                return $action
                                    ->modalHeading('Crear Nuevo Usuario')
                                    ->modalWidth('md');
                            })
                            ->disabled(fn (?TenantMembership $record): bool => $record !== null)
                            ->columnSpan(1),
                        Select::make('role')
                            ->label('Rol')
                            ->options(static::getRoleOptions())
                            ->default(Role::OPERATOR->value)
                            ->required()
                            ->native(false)
                            ->helperText(static::getRoleHelperText())
                            ->disabled(fn (?TenantMembership $record): bool => $record?->isOwner() ?? false)
                            ->columnSpan(1),
                    ]),
                Section::make('Informacion de Acceso')
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn (?TenantMembership $record): bool => $record !== null)
                    ->columns(2)
                    ->schema([
                        Placeholder::make('user_email_display')
                            ->label('Email')
                            ->content(function (?TenantMembership $record): string {
                                $user = $record?->user;

                                return $user instanceof User ? $user->email : 'N/A';
                            }),
                        Placeholder::make('assigned_at_display')
                            ->label('Asignado')
                            ->content(function (?TenantMembership $record): string {
                                $createdAt = $record?->created_at;

                                return $createdAt instanceof Carbon ? $createdAt->format('d/m/Y H:i') : 'N/A';
                            }),
                        Placeholder::make('accepted_at_display')
                            ->label('Estado')
                            ->content(function (?TenantMembership $record): string {
                                $acceptedAt = $record?->getAttribute('accepted_at');

                                if (is_string($acceptedAt) && $acceptedAt !== '') {
                                    return 'Activo desde '.Carbon::parse($acceptedAt)->format('d/m/Y H:i');
                                }

                                return 'Pendiente';
                            }),
                        Placeholder::make('preset_guidance')
                            ->label('Presets disponibles')
                            ->content(static::getActivePresetSummary()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $tenant = static::getTenantRecord();

                if (! $tenant instanceof Tenant) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->where('tenant_id', (string) $tenant->getKey())
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
                    ->state(fn (TenantMembership $record): string => filled($record->getAttribute('accepted_at')) ? 'Activo' : 'Pendiente')
                    ->color(fn (TenantMembership $record): string => filled($record->getAttribute('accepted_at')) ? 'success' : 'warning'),
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
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (TenantMembership $record): bool => ! $record->isOwner()),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavia no has agregado miembros',
                'description' => 'Agrega personas y asignales un rol claro dentro del negocio.',
                'buttonLabel' => 'Agregar miembro',
                'buttonUrl' => static::getUrl('create'),
            ]));
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

    public static function getRoleOptions(): array
    {
        return collect(array_keys(config('rbac.roles', [])))
            ->filter(static fn (mixed $value): bool => is_string($value) && Role::tryFrom($value) !== null)
            ->mapWithKeys(static fn (string $value): array => [$value => static::getRoleLabel($value)])
            ->all();
    }

    public static function getRoleLabel(Role|string|null $role): string
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

    public static function getRoleColor(Role|string|null $role): string
    {
        $value = $role instanceof Role ? $role->value : (string) $role;

        return match ($value) {
            Role::OWNER->value => 'warning',
            Role::ADMIN->value, Role::MANAGER->value => 'info',
            Role::OPERATOR->value => 'success',
            Role::VIEWER->value => 'gray',
            default => 'gray',
        };
    }

    public static function getRoleHelperText(): string
    {
        $summary = static::getActivePresetSummary();

        if ($summary === 'Sin presets adicionales para este negocio.') {
            return 'Asigna uno de los roles base. Los presets verticales se apoyaran sobre estos mismos cimientos.';
        }

        return $summary;
    }

    public static function getActivePresetSummary(): string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
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

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
