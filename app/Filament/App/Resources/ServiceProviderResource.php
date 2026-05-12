<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ServiceProviderResource\Pages;
use App\Models\ServiceProvider;
use App\Support\Product\TenantAccessMatrix;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceProviderResource extends Resource
{
    protected static ?string $model = ServiceProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Equipo de atencion';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'miembro del equipo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'equipo de atencion';
    }

    public static function canViewAny(): bool
    {
        return TenantAccessMatrix::currentTenantCanAccess(TenantAccessMatrix::MODULE_SERVICE_PROVIDERS);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacion del equipo')
                    ->description('Registra a las personas que pueden atender reservas y citas.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre completo')
                            ->placeholder('Ej. Ana Hernandez')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo electronico')
                            ->placeholder('ana@negocio.com')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefono')
                            ->placeholder('9999-9999')
                            ->tel(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible para reservas')
                            ->helperText('Desactivalo si esta persona no debe aparecer como opcion al reservar.')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Horario personalizado')
                    ->description('Opcional. Define un horario especifico para esta persona. Si queda vacio, se usa el horario general del negocio.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Forms\Components\KeyValue::make('schedule_config')
                            ->label('Disponibilidad por dia')
                            ->keyLabel('Dia en ingles')
                            ->valueLabel('Horario')
                            ->keyPlaceholder('monday')
                            ->valuePlaceholder('09:00-17:00')
                            ->reorderable()
                            ->addActionLabel('Agregar dia')
                            ->helperText('Dias validos: monday, tuesday, wednesday, thursday, friday, saturday, sunday. Formato: HH:MM-HH:MM.'),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-o-envelope')
                    ->placeholder('Sin correo'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefono')
                    ->icon('heroicon-o-phone')
                    ->placeholder('Sin telefono'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Disponible')
                    ->boolean(),

                Tables\Columns\TextColumn::make('appointments_count')
                    ->label('Citas')
                    ->counts('appointments')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Disponibilidad')
                    ->placeholder('Todo el equipo')
                    ->trueLabel('Disponible')
                    ->falseLabel('No disponible'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar miembro del equipo')
                    ->modalDescription('Esta accion quita a la persona de las opciones de atencion. Las citas existentes no se modifican.')
                    ->modalSubmitActionLabel('Eliminar miembro'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar miembros seleccionados')
                        ->modalDescription('Esta accion quita a estas personas de las opciones de atencion para nuevas reservas.')
                        ->modalSubmitActionLabel('Eliminar miembros'),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('Aun no hay equipo disponible')
            ->emptyStateDescription('Agrega a la primera persona que atendera reservas y citas del negocio.')
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
            'index' => Pages\ListServiceProviders::route('/'),
            'create' => Pages\CreateServiceProvider::route('/create'),
            'edit' => Pages\EditServiceProvider::route('/{record}/edit'),
        ];
    }
}
