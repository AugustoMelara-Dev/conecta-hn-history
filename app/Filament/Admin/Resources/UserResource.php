<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Plataforma';
    protected static ?string $navigationLabel = 'Usuarios globales';
    protected static ?string $modelLabel = 'usuario';
    protected static ?string $pluralModelLabel = 'Usuarios globales';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $schema->schema([
            Section::make('Identidad')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->label('Nombre')->required(),
                    TextInput::make('email')->label('Email')->email()->required(),
                    Select::make('tenant_id')->label('Tenant')->relationship('tenant', 'name')->searchable(),
                    Select::make('roles')->label('Roles')->multiple()->relationship('roles', 'name'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('tenant.name')->label('Tenant')->placeholder('—'),
                IconColumn::make('is_super_admin')->label('Super admin')->boolean(),
                IconColumn::make('mfa_enabled')->label('MFA')->boolean(),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y')->sortable(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('impersonate')
                    ->label('Impersonar')
                    ->icon('heroicon-o-finger-print')
                    ->color('warning')
                    ->action(function ($record) {
                        auth()->login($record);
                        Notification::make()->title('Impersonando a ' . $record->name)->warning()->send();
                    }),
                Action::make('reset_mfa')
                    ->label('Reset MFA')
                    ->icon('heroicon-o-key')
                    ->action(function ($record) {
                        $record->update(['mfa_secret' => null, 'mfa_enabled' => false]);
                        Notification::make()->title('MFA reseteado')->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}



