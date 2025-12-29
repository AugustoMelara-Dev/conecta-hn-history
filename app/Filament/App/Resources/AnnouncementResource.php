<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * AnnouncementResource - Filament Admin Interface for Scheduled Banners
 *
 * Features:
 * - Restricted RichEditor (no images)
 * - Type-based color preview
 * - Quick toggle for activation
 * - Honduras timezone compliance
 *
 * @package App\Filament\App\Resources
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Anuncios';

    protected static ?string $modelLabel = 'Anuncio';

    protected static ?string $pluralModelLabel = 'Anuncios';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Contenido del Anuncio')
                    ->description('Configure el mensaje y tipo de banner')
                    ->schema([
                        // Type Selector with color preview
                        Select::make('type')
                            ->label('Tipo de Anuncio')
                            ->required()
                            ->live()
                            ->options([
                                Announcement::TYPE_INFO => '🔵 Información (Azul)',
                                Announcement::TYPE_WARNING => '🟡 Advertencia (Amarillo)',
                                Announcement::TYPE_PROMO => '🟢 Promoción (Verde)',
                            ])
                            ->default(Announcement::TYPE_INFO)
                            ->helperText(fn (Get $get): string => match ($get('type')) {
                                Announcement::TYPE_INFO => 'Se mostrará con fondo azul - ideal para avisos generales',
                                Announcement::TYPE_WARNING => 'Se mostrará con fondo amarillo - ideal para avisos importantes',
                                Announcement::TYPE_PROMO => 'Se mostrará con gradiente verde - ideal para promociones',
                                default => 'Seleccione un tipo de anuncio',
                            }),

                        // Content - Restricted RichEditor
                        RichEditor::make('content')
                            ->label('Contenido')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'link',
                                'bulletList',
                            ])
                            ->helperText('Solo estilos básicos permitidos. Las imágenes están deshabilitadas.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Programación')
                    ->description('Configure cuándo se mostrará el anuncio')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Start Date
                                DateTimePicker::make('starts_at')
                                    ->label('Fecha de Inicio')
                                    ->required()
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i')
                                    ->default(now())
                                    ->helperText('Cuándo comenzará a mostrarse'),

                                // End Date
                                DateTimePicker::make('expires_at')
                                    ->label('Fecha de Expiración')
                                    ->required()
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i')
                                    ->after('starts_at')
                                    ->helperText('Cuándo dejará de mostrarse'),

                                // Active Toggle
                                Toggle::make('is_active')
                                    ->label('Anuncio Activo')
                                    ->default(true)
                                    ->helperText('Desactivar para pausar el anuncio sin eliminarlo')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Type Badge (null-safe for consistency)
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Announcement::TYPE_INFO => 'Info',
                        Announcement::TYPE_WARNING => 'Aviso',
                        Announcement::TYPE_PROMO => 'Promo',
                        default => $state ?? '',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Announcement::TYPE_INFO => 'info',
                        Announcement::TYPE_WARNING => 'warning',
                        Announcement::TYPE_PROMO => 'success',
                        default => 'gray',
                    }),

                // Content Preview - Stripped HTML, Limited
                TextColumn::make('content')
                    ->label('Contenido')
                    ->limit(50)
                    ->html(false)
                    ->formatStateUsing(fn (?string $state): string => $state ? strip_tags($state) : '')
                    ->wrap()
                    ->searchable(),

                // Status Badge (computed attribute - needs getStateUsing)
                TextColumn::make('status')
                    ->label('Estado')
                    ->getStateUsing(fn (Announcement $record): string => $record->status)
                    ->badge()
                    ->formatStateUsing(fn (Announcement $record): string => $record->status_label)
                    ->color(fn (Announcement $record): string => $record->status_color),

                // Schedule Period
                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->sortable(),

                // Quick Toggle
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
                    ->label('Activos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
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
