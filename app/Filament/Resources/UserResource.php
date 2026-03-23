<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = User::class;

    protected static ?string $modelPolicy = \App\Policies\UserPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return 'Usuarios';
    }

    public static function getModelLabel(): string
    {
        return 'Usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Usuarios';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gerencia';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->label('Rol')
                    ->options([
                        UserRole::TENANT_OWNER->value => 'Dueno de negocio',
                        UserRole::STAFF->value => 'Personal',
                        UserRole::CUSTOMER->value => 'Cliente',
                        UserRole::SUPER_ADMIN->value => 'Superadministrador',
                    ])
                    ->default(UserRole::CUSTOMER->value),
                Forms\Components\TextInput::make('password')
                    ->label('Contrasena')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (UserRole|string|null $state): string => static::getRoleColor($state))
                    ->formatStateUsing(fn (UserRole|string|null $state): string => static::getRoleLabel($state)),
                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Negocios')
                    ->counts('tenants')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        UserRole::TENANT_OWNER->value => 'Dueno de negocio',
                        UserRole::STAFF->value => 'Personal',
                        UserRole::CUSTOMER->value => 'Cliente',
                        UserRole::SUPER_ADMIN->value => 'Superadministrador',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('change_password')
                    ->label('Cambiar contrasena')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('Nueva contrasena')
                            ->password()
                            ->required()
                            ->minLength(8),
                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('Confirmar contrasena')
                            ->password()
                            ->required()
                            ->same('new_password'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => Hash::make($data['new_password']),
                        ]);

                        Notification::make()->title('Contrasena actualizada')->success()->send();
                    }),
                Action::make('ban')
                    ->label('Banear')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => is_null($record->banned_at) && $record->id !== auth()->id())
                    ->action(function (User $record): void {
                        $record->update(['banned_at' => now()]);
                        Notification::make()->title('Usuario baneado')->success()->send();
                    }),
                Action::make('unban')
                    ->label('Desbanear')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! is_null($record->banned_at))
                    ->action(function (User $record): void {
                        $record->update(['banned_at' => null]);
                        Notification::make()->title('Usuario desbaneado')->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    private static function normalizeRole(UserRole|string|null $state): ?string
    {
        if ($state instanceof UserRole) {
            return $state->value;
        }

        return is_string($state) && $state !== '' ? $state : null;
    }

    private static function getRoleLabel(UserRole|string|null $state): string
    {
        return match (static::normalizeRole($state)) {
            UserRole::TENANT_OWNER->value => 'Dueno de negocio',
            UserRole::STAFF->value => 'Personal',
            UserRole::SUPER_ADMIN->value => 'Superadministrador',
            UserRole::CUSTOMER->value => 'Cliente',
            default => 'Usuario',
        };
    }

    private static function getRoleColor(UserRole|string|null $state): string
    {
        return match (static::normalizeRole($state)) {
            UserRole::TENANT_OWNER->value => 'info',
            UserRole::STAFF->value => 'warning',
            UserRole::SUPER_ADMIN->value => 'danger',
            UserRole::CUSTOMER->value => 'gray',
            default => 'gray',
        };
    }
}
