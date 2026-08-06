<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Campañas';

    protected static ?string $modelLabel = 'campaña';

    protected static ?string $pluralModelLabel = 'Campañas';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información de la campaña')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label('Tipo')
                            ->required()
                            ->options([
                                'email' => 'Email',
                                'sms' => 'SMS',
                                'whatsapp' => 'WhatsApp',
                                'push' => 'Push notification',
                            ])
                            ->default('email'),

                        Select::make('status')
                            ->label('Estado')
                            ->required()
                            ->options([
                                'draft' => 'Borrador',
                                'scheduled' => 'Programada',
                                'running' => 'En curso',
                                'paused' => 'Pausada',
                                'completed' => 'Completada',
                            ])
                            ->default('draft'),
                    ]),

                Section::make('Contenido')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Contenido')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Programación')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('scheduled_at')
                                    ->label('Programada para')
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i'),

                                TextInput::make('target_audience_size')
                                    ->label('Tamaño de audiencia')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_active')
                                    ->label('Activa')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'email' => 'info',
                        'sms' => 'warning',
                        'whatsapp' => 'success',
                        'push' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'info',
                        'running' => 'success',
                        'paused' => 'warning',
                        'completed' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('scheduled_at')
                    ->label('Programada')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->sortable(),

                TextColumn::make('target_audience_size')
                    ->label('Audiencia')
                    ->numeric()
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Activa'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'push' => 'Push',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No tienes campañas creadas')
            ->emptyStateDescription('Crea tu primera campaña para empezar a comunicarte con tus clientes.')
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Crear primera campaña')
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('business_id', $tenant->getKey());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}



