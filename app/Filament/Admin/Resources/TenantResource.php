<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Plataforma';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'tenant';

    protected static ?string $pluralModelLabel = 'Tenants';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información')
                    ->components([
                        Grid::make(2)->components([
                            TextInput::make('name')->label('Nombre')->required(),
                            TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),
                            TextInput::make('email')->label('Email')->email(),
                            TextInput::make('phone')->label('Teléfono'),
                            Select::make('plan')->label('Plan')->options(['free' => 'Free', 'pro' => 'Pro', 'enterprise' => 'Enterprise'])->default('free'),
                            Select::make('status')->label('Estado')->options(['active' => 'Activo', 'banned' => 'Baneado', 'suspended' => 'Suspendido'])->default('active'),
                        ]),
                    ]),
                Section::make('Configuración')->components([
                    Grid::make(2)->components([
                        Toggle::make('is_active')->label('Activo')->default(true),
                        DateTimePicker::make('trial_ends_at')->label('Trial hasta'),
                        Textarea::make('notes')->label('Notas internas')->rows(3)->columnSpanFull(),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->weight('medium'),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('plan')->label('Plan')->badge(),
                TextColumn::make('status')->label('Estado')->badge(),
                IconColumn::make('is_active')->label('Activo')->boolean(),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('ban')
                    ->label('Banear')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'banned', 'is_active' => false]);
                        Notification::make()->title('Tenant baneado')->success()->send();
                    }),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}

