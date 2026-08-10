<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
// MIGRATION: Updated to Domain namespace (2026-01-05)
use App\Models\User;
use App\Policies\UserPolicy;
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
    protected static ?string $model = User::class;

    protected static ?string $modelPolicy = UserPolicy::class;

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
        return 'Gestión de Usuarios';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                        'seller' => 'Vendedor',
                        'buyer' => 'Comprador',
                        'super_admin' => 'Super Admin',
                    ])
                    ->default('buyer'),
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
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
                    ->color(fn (UserRole|string|null $state): string => match ($state instanceof UserRole ? $state->value : $state) {
                        'seller' => 'info',
                        'user', 'buyer' => 'gray',
                        'super_admin' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (UserRole|string|null $state): string => match ($state instanceof UserRole ? $state->value : $state) {
                        'seller' => 'Vendedor',
                        'user', 'buyer' => 'Comprador',
                        'super_admin' => 'Super Admin',
                        'staff' => 'Colaborador',
                        default => 'Usuario',
                    }),
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
                        'seller' => 'Vendedor',
                        'buyer' => 'Comprador',
                        'super_admin' => 'Super Admin',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('change_password')
                    ->label('Cambiar Contraseña')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('Nueva Contraseña')
                            ->password()
                            ->required()
                            ->minLength(8),
                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->required()
                            ->same('new_password'),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'password' => Hash::make($data['new_password']),
                        ]);
                        Notification::make()->title('Contraseña actualizada')->success()->send();
                    }),

                Action::make('ban')
                    ->label('Banear')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => is_null($record->banned_at) && $record->id !== auth()->id())
                    ->action(function (User $record) {
                        $record->update(['banned_at' => now()]);
                        Notification::make()->title('Usuario baneado')->success()->send();
                    }),

                Action::make('unban')
                    ->label('Desbanear')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! is_null($record->banned_at))
                    ->action(function (User $record) {
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
