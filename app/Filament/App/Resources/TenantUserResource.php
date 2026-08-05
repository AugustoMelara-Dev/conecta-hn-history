<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Identity\TenantInvitationService;
use App\Application\Identity\TenantMembershipService;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Filament\App\Resources\TenantUserResource\Pages;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantUserResource extends Resource
{
    protected static ?string $model = TenantMembership::class;

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

    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        return $tenant instanceof Tenant
            && $user instanceof User
            && app(TenantInvitationService::class)->canManageTeam(
                (string) $tenant->getKey(),
                (string) $user->getKey(),
            );
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->when(
                $tenant instanceof Tenant,
                fn (Builder $query): Builder => $query->where('tenant_id', $tenant->getKey()),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identidad y rol')
                ->description('Dovra enviará una invitación de acceso. La persona no obtiene acceso hasta aceptarla con este mismo correo.')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->maxLength(254)
                        ->autocomplete('email')
                        ->required()
                        ->visible(fn (?TenantMembership $record): bool => $record === null),

                    Forms\Components\Placeholder::make('member_identity')
                        ->label('Cuenta')
                        ->content(fn (?TenantMembership $record): string => $record?->user === null
                            ? 'Cuenta no disponible'
                            : "{$record->user->name} · {$record->user->email}")
                        ->visible(fn (?TenantMembership $record): bool => $record !== null),

                    Forms\Components\Select::make('role')
                        ->label('Rol')
                        ->options(self::roleOptions())
                        ->default(Role::OPERATOR->value)
                        ->required(),

                    Forms\Components\TextInput::make('preset_key')
                        ->label('Preset')
                        ->maxLength(64)
                        ->placeholder('operations')
                        ->helperText('Identificador opcional de un preset de permisos.'),
                ]),

            Forms\Components\Section::make('Permisos adicionales')
                ->description('Solo se pueden delegar permisos que la persona que invita ya posee.')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Forms\Components\CheckboxList::make('granted_permissions')
                        ->label('Permisos')
                        ->options(self::permissionOptions())
                        ->columns(2)
                        ->bulkToggleable(),
                ]),

            Forms\Components\Section::make('Estado')
                ->icon('heroicon-o-information-circle')
                ->visible(fn (?TenantMembership $record): bool => $record !== null)
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('status_display')
                        ->label('Estado')
                        ->content(fn (?TenantMembership $record): string => self::statusLabel($record?->status)),
                    Forms\Components\Placeholder::make('invited_at_display')
                        ->label('Invitado')
                        ->content(fn (?TenantMembership $record): string => $record?->invited_at?->format('d/m/Y H:i') ?? 'No registrado'),
                    Forms\Components\Placeholder::make('accepted_at_display')
                        ->label('Aceptado')
                        ->content(fn (?TenantMembership $record): string => $record?->accepted_at?->format('d/m/Y H:i') ?? 'Pendiente'),
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
                        'admin' => 'danger',
                        'manager' => 'info',
                        'operator' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state))
                    ->color(fn (mixed $state): string => match ((string) $state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'info',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Aceptado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),

                Tables\Columns\TextColumn::make('permissions_summary')
                    ->label('Permisos adicionales')
                    ->state(fn (TenantMembership $record): string => self::permissionsSummary($record)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options(self::roleOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'pending' => 'Pendiente',
                        'suspended' => 'Suspendido',
                        'revoked' => 'Revocado',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (TenantMembership $record): bool => ! $record->isOwner() && $record->status === 'active'),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (TenantMembership $record): bool => ! $record->isOwner() && $record->status === 'active')
                    ->action(function (TenantMembership $record): void {
                        app(TenantMembershipService::class)->suspend(
                            (string) $record->getKey(),
                            (string) auth()->id(),
                        );
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivar')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TenantMembership $record): bool => ! $record->isOwner() && $record->status === 'suspended')
                    ->action(function (TenantMembership $record): void {
                        app(TenantMembershipService::class)->reactivate(
                            (string) $record->getKey(),
                            (string) auth()->id(),
                        );
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label('Revocar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TenantMembership $record): bool => ! $record->isOwner()
                        && in_array($record->status, ['active', 'suspended'], true))
                    ->action(function (TenantMembership $record): void {
                        app(TenantMembershipService::class)->revoke(
                            (string) $record->getKey(),
                            (string) auth()->id(),
                        );
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Aún no hay miembros del equipo')
            ->emptyStateDescription('Invita miembros con un rol y permisos explícitos.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Invitar miembro'),
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

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        return [
            Role::ADMIN->value => 'Administrador',
            Role::MANAGER->value => 'Gerente',
            Role::OPERATOR->value => 'Operador',
            Role::VIEWER->value => 'Solo lectura',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function permissionOptions(): array
    {
        return collect(Permission::cases())
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->value => str($permission->value)
                    ->replace('.', ' · ')
                    ->replace('_', ' ')
                    ->title()
                    ->value(),
            ])
            ->all();
    }

    private static function roleValue(mixed $role): string
    {
        return $role instanceof BackedEnum ? (string) $role->value : (string) $role;
    }

    private static function roleLabel(mixed $role): string
    {
        return match (self::roleValue($role)) {
            'owner' => 'Propietario',
            'admin' => 'Administrador',
            'manager' => 'Gerente',
            'operator' => 'Operador',
            'viewer' => 'Solo lectura',
            default => 'Miembro',
        };
    }

    private static function statusLabel(mixed $status): string
    {
        return match ((string) $status) {
            'active' => 'Activo',
            'pending' => 'Pendiente',
            'suspended' => 'Suspendido',
            'revoked' => 'Revocado',
            default => 'Desconocido',
        };
    }

    private static function permissionsSummary(TenantMembership $record): string
    {
        $permissions = $record->extraPermissions();

        return $permissions === [] ? 'Ninguno' : implode(', ', $permissions);
    }
}
