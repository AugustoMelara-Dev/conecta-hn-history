<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $tenantOwnershipRelationshipName = 'business';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Avisos';

    protected static ?string $modelLabel = 'aviso';

    protected static ?string $pluralModelLabel = 'Avisos';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenido del aviso')
                    ->description('Define el mensaje que verán tus clientes en las superficies públicas.')
                    ->components([
                        Select::make('type')
                            ->label('Tipo de aviso')
                            ->required()
                            ->live()
                            ->options([
                                Announcement::TYPE_INFO => 'Información',
                                Announcement::TYPE_WARNING => 'Advertencia',
                                Announcement::TYPE_PROMO => 'Promoción',
                            ])
                            ->default(Announcement::TYPE_INFO)
                            ->helperText(fn (Get $get): string => match ($get('type')) {
                                Announcement::TYPE_INFO => 'Úsalo para horarios especiales, novedades o mensajes generales.',
                                Announcement::TYPE_WARNING => 'Úsalo para cambios importantes, cierres temporales o condiciones de servicio.',
                                Announcement::TYPE_PROMO => 'Úsalo para promociones, descuentos o temporadas especiales.',
                                default => 'Selecciona un tipo de aviso.',
                            }),

                        Textarea::make('content')
                            ->label('Mensaje')
                            ->required()
                            ->rows(4)
                            ->maxLength(500)
                            ->helperText('Usa texto breve y directo. Evita incluir datos sensibles o instrucciones que cambien el precio final.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Programación')
                    ->description('Define cuándo debe aparecer y cuándo debe dejar de mostrarse.')
                    ->components([
                        Grid::make(2)
                            ->components([
                                DateTimePicker::make('starts_at_utc')
                                    ->label('Fecha de inicio')
                                    ->required()
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i')
                                    ->default(now())
                                    ->helperText('Momento en que el aviso comenzará a mostrarse.'),

                                DateTimePicker::make('expires_at_utc')
                                    ->label('Fecha de expiración')
                                    ->required()
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i')
                                    ->after('starts_at_utc')
                                    ->helperText('Momento en que el aviso dejará de mostrarse.'),

                                Toggle::make('is_active')
                                    ->label('Aviso activo')
                                    ->default(true)
                                    ->helperText('Desactívalo para pausar el aviso sin eliminarlo.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Announcement::TYPE_INFO => 'Información',
                        Announcement::TYPE_WARNING => 'Advertencia',
                        Announcement::TYPE_PROMO => 'Promoción',
                        default => $state ?? '',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Announcement::TYPE_INFO => 'info',
                        Announcement::TYPE_WARNING => 'warning',
                        Announcement::TYPE_PROMO => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('content')
                    ->label('Mensaje')
                    ->limit(70)
                    ->html(false)
                    ->formatStateUsing(fn (?string $state): string => $state ? strip_tags($state) : '')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->getStateUsing(fn (Announcement $record): string => $record->status)
                    ->badge()
                    ->color(fn (Announcement $record): string => $record->status_color),

                TextColumn::make('starts_at_utc')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->sortable(),

                TextColumn::make('expires_at_utc')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Activo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        Announcement::TYPE_INFO => 'Información',
                        Announcement::TYPE_WARNING => 'Advertencia',
                        Announcement::TYPE_PROMO => 'Promoción',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Pausados'),
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
            ->emptyStateHeading('No tienes avisos creados')
            ->emptyStateDescription('Crea un aviso para comunicar horarios especiales, promociones o mensajes importantes a tus clientes.')
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Crear primer aviso')
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('starts_at_utc', 'desc');
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
