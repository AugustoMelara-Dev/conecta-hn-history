<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * CouponResource - Promotional Discount Management
 *
 * Allows business owners to create and manage discount campaigns
 * with rules for minimum spend, usage limits, and date ranges.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Cupones';

    protected static ?string $modelLabel = 'Cupón';

    protected static ?string $pluralModelLabel = 'Cupones';

    protected static string|\UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // ═══════════════════════════════════════════════════════════════════════
                // MAIN SECTION: Code, Type, Value
                // ═══════════════════════════════════════════════════════════════════════
                Section::make('Información del Cupón')
                    ->description('Configura el código y el tipo de descuento')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Código')
                                ->required()
                                ->maxLength(50)
                                ->placeholder('VERANO2024')
                                ->helperText('Se convertirá a mayúsculas automáticamente')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->unique(
                                    table: 'coupons',
                                    column: 'code',
                                    ignoreRecord: true,
                                    modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                                        $tenant = \Filament\Facades\Filament::getTenant();
                                        if ($tenant) {
                                            return $rule->where('tenant_id', $tenant->id);
                                        }

                                        return $rule;
                                    }
                                ),

                            TextInput::make('name')
                                ->label('Nombre interno')
                                ->maxLength(255)
                                ->placeholder('Promoción de verano'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label('Tipo de descuento')
                                ->options([
                                    'fixed' => '💵 Monto fijo (L.)',
                                    'percent' => '📊 Porcentaje (%)',
                                ])
                                ->required()
                                ->live()
                                ->default('fixed'),

                            TextInput::make('value')
                                ->label('Valor')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->suffix(fn ($get) => $get('type') === 'percent' ? '%' : 'L.')
                                ->helperText(fn ($get) => $get('type') === 'percent'
                                    ? 'Ej: 10 para 10% de descuento'
                                    : 'Ej: 50 para L. 50 de descuento'),
                        ]),

                        TextInput::make('max_discount')
                            ->label('Descuento máximo')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('L.')
                            ->helperText('Para cupones de porcentaje: límite del descuento (opcional)')
                            ->visible(fn ($get) => $get('type') === 'percent'),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(2)
                            ->placeholder('Descripción interna para referencia'),
                    ]),

                // ═══════════════════════════════════════════════════════════════════════
                // RESTRICTIONS SECTION
                // ═══════════════════════════════════════════════════════════════════════
                Section::make('Restricciones')
                    ->description('Configura las reglas de uso del cupón')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('min_spend')
                                ->label('Gasto mínimo')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('L.')
                                ->placeholder('Sin mínimo')
                                ->helperText('Monto mínimo del carrito para aplicar'),

                            TextInput::make('usage_limit')
                                ->label('Límite de usos')
                                ->numeric()
                                ->minValue(1)
                                ->placeholder('Ilimitado')
                                ->helperText('Número máximo de veces que se puede usar'),
                        ]),

                        TextInput::make('usage_count')
                            ->label('Usos actuales')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),
                    ]),

                // ═══════════════════════════════════════════════════════════════════════
                // VALIDITY SECTION
                // ═══════════════════════════════════════════════════════════════════════
                Section::make('Vigencia')
                    ->description('Define el período de validez del cupón')
                    ->schema([
                        Grid::make(2)->schema([
                            DateTimePicker::make('starts_at')
                                ->label('Fecha de inicio')
                                ->placeholder('Inmediato')
                                ->helperText('Dejar vacío para activar inmediatamente'),

                            DateTimePicker::make('expires_at')
                                ->label('Fecha de expiración')
                                ->placeholder('Sin expiración')
                                ->helperText('Dejar vacío para no expirar'),
                        ]),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Desactiva para pausar temporalmente el cupón'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Código copiado'),

                TextColumn::make('formatted_value')
                    ->label('Descuento')
                    ->badge()
                    ->color(fn ($record) => $record->type === 'percent' ? 'info' : 'success'),

                TextColumn::make('min_spend')
                    ->label('Mín.')
                    ->money('HNL')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('usage_display')
                    ->label('Usos')
                    ->badge()
                    ->color(fn ($record) => $record->usage_limit && $record->usage_count >= $record->usage_limit
                            ? 'danger'
                            : 'gray'
                    ),

                TextColumn::make('status_label')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d M Y')
                    ->placeholder('Sin expiración')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'fixed' => 'Monto fijo',
                        'percent' => 'Porcentaje',
                    ]),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'expired' => 'Expirado',
                        'depleted' => 'Agotado',
                        'inactive' => 'Inactivo',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'active' => $query->valid(),
                            'expired' => $query->where('expires_at', '<', now()),
                            'depleted' => $query->whereColumn('usage_count', '>=', 'usage_limit'),
                            'inactive' => $query->where('is_active', false),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('toggle')
                        ->label(fn ($record) => $record->is_active ? 'Desactivar' : 'Activar')
                        ->icon(fn ($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                        ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                        ->action(fn ($record) => $record->update(['is_active' => ! $record->is_active])),

                    Tables\Actions\Action::make('duplicate')
                        ->label('Duplicar')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(function ($record) {
                            $new = $record->replicate();
                            $new->code = $record->code.'_COPIA';
                            $new->usage_count = 0;
                            $new->save();
                        }),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    use \App\Filament\Traits\ScopesToTenant;

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    /**
     * Navigation badge: Active coupons count
     */
    public static function getNavigationBadge(): ?string
    {
        $query = static::getEloquentQuery();

        return $query->valid()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
