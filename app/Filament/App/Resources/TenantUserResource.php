<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Services\BusinessOperationsBlueprint;
use App\Domain\TenantGovernance\Services\BusinessRolePresetCatalog;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantUserResource extends Resource
{
    protected static ?string $model = TenantMembership::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();

        return $tenant instanceof Tenant
            && $tenant->hasCapability(Capability::STAFF_BASIC)
            && Gate::allows('viewAny', TenantMembership::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
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
                Section::make('Información del miembro')
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
                                    ->label('Contraseña temporal')
                                    ->password()
                                    ->required()
                                    ->minLength(8)
                                    ->helperText('Se guarda de forma segura y la persona podrá cambiarla luego.'),
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
                                    ->modalHeading('Crear nuevo usuario')
                                    ->modalWidth('md');
                            })
                            ->disabled(fn (?TenantMembership $record): bool => $record !== null)
                            ->columnSpan(1),

                        Select::make('role')
                            ->label('Rol base')
                            ->options(static::getRoleOptions())
                            ->default(Role::OPERATOR->value)
                            ->required()
                            ->native(false)
                            ->helperText(static::getRoleHelperText())
                            ->disabled(fn (?TenantMembership $record): bool => $record?->isOwner() ?? false)
                            ->columnSpan(1),

                        Select::make('preset_key')
                            ->label('Perfil operativo')
                            ->options(static::getPresetOptions())
                            ->placeholder('Usar solo el rol base')
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText(static::getPresetHelperText())
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $tenant = static::getTenantRecord();

                                if (! $tenant instanceof Tenant || ! is_string($state) || $state === '') {
                                    return;
                                }

                                $preset = app(BusinessRolePresetCatalog::class)->findForTenant($tenant, $state);

                                if (is_array($preset) && isset($preset['role']) && is_string($preset['role'])) {
                                    $set('role', $preset['role']);
                                }
                            })
                            ->columnSpan(1),

                        Placeholder::make('preset_description')
                            ->label('Resumen del perfil')
                            ->content(function (Get $get): string {
                                $presetKey = $get('preset_key');

                                return static::getPresetDescription(is_string($presetKey) ? $presetKey : null);
                            })
                            ->columnSpan(1),
                    ]),

                Section::make('Información de acceso')
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
                            ->label('Presets y equipo sugerido')
                            ->content(static::getRoleHelperText()),
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
                Tables\Columns\TextColumn::make('preset_key')
                    ->label('Perfil')
                    ->badge()
                    ->color('info')
                    ->state(fn (TenantMembership $record): string => static::getPresetLabel($record->getAttribute('preset_key'))),
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
                Tables\Filters\SelectFilter::make('preset_key')
                    ->label('Perfil operativo')
                    ->options(static::getPresetOptions()),
            ])
            ->actions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (TenantMembership $record): bool => ! $record->isOwner()),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavía no has agregado miembros',
                'description' => static::getEmptyStateDescription(),
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
        $tenant = static::getTenantRecord();
        $assignableRoles = static::resolveAssignableRoles($tenant);

        return collect(array_keys(config('rbac.roles', [])))
            ->filter(static fn (mixed $value): bool => is_string($value) && Role::tryFrom($value) !== null)
            ->filter(static fn (string $value): bool => in_array($value, $assignableRoles, true))
            ->mapWithKeys(static fn (string $value): array => [$value => static::getRoleLabel($value)])
            ->all();
    }

    public static function getRoleLabel(Role|string|null $role): string
    {
        $value = $role instanceof Role ? $role->value : (string) $role;

        return match ($value) {
            Role::OWNER->value => 'Dueño',
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
        $teamSummary = static::getVerticalTeamSummary();

        if ($summary === 'Sin presets adicionales para este negocio.' && $teamSummary === 'Sin recomendación vertical todavía.') {
            return 'Asigna un rol base y, si aplica, un perfil operativo por rubro.';
        }

        $combined = collect([$summary, $teamSummary])
            ->filter(static fn (string $item): bool => ! in_array($item, ['Sin presets adicionales para este negocio.', 'Sin recomendación vertical todavía.'], true))
            ->implode(' ');

        return $combined !== '' ? $combined : 'Asigna un rol base y, si aplica, un perfil operativo por rubro.';
    }

    public static function getPresetHelperText(): string
    {
        if (static::getPresetOptions() === []) {
            return 'Este rubro todavía no tiene perfiles operativos extra.';
        }

        return 'Los perfiles operativos agregan permisos finos para que cada vertical trabaje con claridad.';
    }

    public static function getActivePresetSummary(): string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return 'Sin contexto de tenant.';
        }

        $presets = collect(static::getPresetCatalog()->forTenant($tenant))
            ->map(function (array $preset): string {
                $role = Role::tryFrom((string) ($preset['role'] ?? ''));
                $roleLabel = $role ? static::getRoleLabel($role) : 'Rol base';

                return ((string) ($preset['label'] ?? 'Perfil')).' -> '.$roleLabel;
            })
            ->values()
            ->all();

        if ($presets === []) {
            return 'Sin presets adicionales para este negocio.';
        }

        return 'Perfiles listos para este negocio: '.implode(', ', $presets).'.';
    }

    public static function getVerticalTeamSummary(): string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return 'Sin contexto de tenant.';
        }

        $blueprint = app(BusinessOperationsBlueprint::class)->forTenant($tenant);
        $teamPlaybook = collect((array) ($blueprint['team_playbook'] ?? []))
            ->map(function (array $item): string {
                $title = (string) ($item['title'] ?? '');
                $role = (string) ($item['role'] ?? '');

                if ($title === '') {
                    return '';
                }

                return $title.' -> '.static::getRoleLabel($role);
            })
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();

        if ($teamPlaybook === []) {
            return 'Sin recomendación vertical todavía.';
        }

        return 'Equipo sugerido por rubro: '.implode(', ', $teamPlaybook).'.';
    }

    public static function getEmptyStateDescription(): string
    {
        $teamSummary = static::getVerticalTeamSummary();

        if (in_array($teamSummary, ['Sin contexto de tenant.', 'Sin recomendación vertical todavía.'], true)) {
            return 'Agrega personas y dales un rol claro dentro del negocio.';
        }

        return 'Agrega personas y dales un rol claro dentro del negocio. '.$teamSummary;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeMembershipData(Tenant $tenant, array $data): array
    {
        $assignableRoles = static::resolveAssignableRoles($tenant);
        if ($assignableRoles === []) {
            throw ValidationException::withMessages([
                'role' => 'No tienes permisos para asignar roles en este negocio.',
            ]);
        }

        $presetKey = isset($data['preset_key']) && is_string($data['preset_key']) && $data['preset_key'] !== ''
            ? $data['preset_key']
            : null;
        $preset = static::getPresetCatalog()->findForTenant($tenant, $presetKey);
        $role = (string) ($data['role'] ?? Role::OPERATOR->value);

        if (is_array($preset) && isset($preset['role']) && is_string($preset['role'])) {
            $role = $preset['role'];
        }

        if (! in_array($role, $assignableRoles, true)) {
            throw ValidationException::withMessages([
                'role' => 'No puedes asignar ese rol con tus permisos actuales.',
            ]);
        }

        return array_merge($data, [
            'role' => $role,
            'preset_key' => $presetKey,
            'granted_permissions' => static::getPresetCatalog()->permissionsForTenantPreset($tenant, $presetKey),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function getPresetOptions(): array
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return collect(static::getPresetCatalog()->forTenant($tenant))
            ->mapWithKeys(static fn (array $preset, string $key): array => [$key => (string) ($preset['label'] ?? $key)])
            ->all();
    }

    protected static function getPresetLabel(?string $presetKey): string
    {
        if (! is_string($presetKey) || trim($presetKey) === '') {
            return 'Sin preset';
        }

        $tenant = static::getTenantRecord();
        $preset = $tenant instanceof Tenant
            ? static::getPresetCatalog()->findForTenant($tenant, $presetKey)
            : null;

        return (string) ($preset['label'] ?? Str::of($presetKey)->replace('_', ' ')->title()->value());
    }

    protected static function getPresetDescription(?string $presetKey): string
    {
        if (! is_string($presetKey) || trim($presetKey) === '') {
            return 'Usa el rol base si todavía no necesitas permisos finos por vertical.';
        }

        $tenant = static::getTenantRecord();
        $preset = $tenant instanceof Tenant
            ? static::getPresetCatalog()->findForTenant($tenant, $presetKey)
            : null;

        return (string) ($preset['description'] ?? 'Perfil operativo recomendado para este rubro.');
    }

    protected static function getPresetCatalog(): BusinessRolePresetCatalog
    {
        return app(BusinessRolePresetCatalog::class);
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function resolveAssignableRoles(?Tenant $tenant): array
    {
        if (! $tenant instanceof Tenant) {
            return [];
        }

        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        if ($actor->isSuperAdmin()) {
            return [
                Role::ADMIN->value,
                Role::MANAGER->value,
                Role::OPERATOR->value,
                Role::VIEWER->value,
            ];
        }

        if ((string) $tenant->user_id === (string) $actor->id) {
            return [
                Role::ADMIN->value,
                Role::MANAGER->value,
                Role::OPERATOR->value,
                Role::VIEWER->value,
            ];
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('user_id', (string) $actor->getKey())
            ->whereNotNull('accepted_at')
            ->first();

        $actorRoleValue = $membership?->getAttribute('role');
        $actorRole = $actorRoleValue instanceof Role
            ? $actorRoleValue
            : Role::tryFrom((string) $actorRoleValue);

        return match ($actorRole) {
            Role::OWNER => [
                Role::ADMIN->value,
                Role::MANAGER->value,
                Role::OPERATOR->value,
                Role::VIEWER->value,
            ],
            Role::ADMIN => [
                Role::MANAGER->value,
                Role::OPERATOR->value,
                Role::VIEWER->value,
            ],
            Role::MANAGER => [
                Role::OPERATOR->value,
                Role::VIEWER->value,
            ],
            default => [],
        };
    }
}
