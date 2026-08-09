<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * CouponResource - Filament Admin Interface for Promotional Coupons
 *
 * Features:
 * - Reactive form (live() state) for dynamic field visibility
 * - Status badges with color coding
 * - Usage tracking display
 * - Honduras timezone compliance
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Cupones';

    protected static ?string $modelLabel = 'Cupón';

    protected static ?string $pluralModelLabel = 'Cupones';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Cupón')
                    ->description('Configure el código y tipo de descuento')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Code Input - Unique, Uppercase
                                TextInput::make('code')
                                    ->label('Código del Cupón')
                                    ->required()
                                    ->unique(
                                        table: 'coupons',
                                        column: 'code',
                                        ignoreRecord: true,
                                        modifyRuleUsing: function (Unique $rule) {
                                            $tenant = Filament::getTenant();
                                            if ($tenant) {
                                                return $rule->where('tenant_id', $tenant->id);
                                            }

                                            return $rule;
                                        }
                                    )
                                    ->maxLength(50)
                                    ->placeholder('Ej: VERANO2024')
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->helperText('El código se convertirá automáticamente a mayúsculas'),

                                // Name
                                TextInput::make('name')
                                    ->label('Nombre Interno')
                                    ->maxLength(100)
                                    ->placeholder('Ej: Promoción Verano'),

                                // Type Selector - REACTIVE (live)
                                Select::make('type')
                                    ->label('Tipo de Descuento')
                                    ->required()
                                    ->live()
                                    ->options([
                                        Coupon::TYPE_PERCENT => 'Porcentaje (%)',
                                        Coupon::TYPE_FIXED => 'Monto Fijo (L.)',
                                        Coupon::TYPE_FREE_SHIPPING => 'Envío Gratis',
                                    ])
                                    ->default(Coupon::TYPE_PERCENT)
                                    ->helperText('El tipo de descuento afecta cómo se aplica el valor'),

                                // Value Input - DYNAMIC visibility and prefix/suffix
                                TextInput::make('value')
                                    ->label('Valor del Descuento')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required(fn (Get $get): bool => $get('type') !== Coupon::TYPE_FREE_SHIPPING)
                                    ->hidden(fn (Get $get): bool => $get('type') === Coupon::TYPE_FREE_SHIPPING)
                                    // L. goes as PREFIX for fixed amounts
                                    ->prefix(fn (Get $get): string => match ($get('type')) {
                                        Coupon::TYPE_FIXED => 'L.',
                                        default => '',
                                    })
                                    // % goes as SUFFIX for percentages
                                    ->suffix(fn (Get $get): string => match ($get('type')) {
                                        Coupon::TYPE_PERCENT => '%',
                                        default => '',
                                    })
                                    ->helperText(fn (Get $get): string => match ($get('type')) {
                                        Coupon::TYPE_PERCENT => 'Porcentaje de descuento (ej: 20 para 20%)',
                                        Coupon::TYPE_FIXED => 'Monto fijo en Lempiras',
                                        default => '',
                                    }),

                                // Max Discount (only for percent type)
                                TextInput::make('max_discount')
                                    ->label('Descuento Máximo')
                                    ->numeric()
                                    ->prefix('L.')
                                    ->hidden(fn (Get $get): bool => $get('type') !== Coupon::TYPE_PERCENT)
                                    ->helperText('Límite máximo del descuento en lempiras'),
                            ]),
                    ]),

                Section::make('Restricciones de Uso')
                    ->description('Configure límites y requisitos mínimos')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Minimum Spend
                                TextInput::make('min_spend')
                                    ->label('Compra Mínima')
                                    ->numeric()
                                    ->prefix('L.')
                                    ->minValue(0)
                                    ->placeholder('0.00')
                                    ->helperText('Monto mínimo del pedido para aplicar el cupón'),

                                // Global Usage Limit
                                TextInput::make('usage_limit')
                                    ->label('Límite de Usos Globales')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->placeholder('Sin límite')
                                    ->helperText('Cantidad máxima de veces que se puede usar este cupón'),

                                // Current Usage (readonly - shows actual value)
                                TextInput::make('usage_count')
                                    ->label('Usos Actuales')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(true) // Keep value on save
                                    ->formatStateUsing(fn (?int $state): string => (string) ($state ?? 0))
                                    ->helperText('Veces que se ha utilizado el cupón'),
                            ]),
                    ]),

                Section::make('Vigencia')
                    ->description('Defina el período de validez del cupón')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Start Date
                                DateTimePicker::make('starts_at')
                                    ->label('Fecha de Inicio')
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i')
                                    ->helperText('Dejar vacío para activar inmediatamente'),

                                // End Date
                                DateTimePicker::make('expires_at')
                                    ->label('Fecha de Expiración')
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i')
                                    ->after('starts_at')
                                    ->helperText('Dejar vacío para no expirar'),

                                // Active Toggle
                                Toggle::make('is_active')
                                    ->label('Cupón Activo')
                                    ->default(true)
                                    ->helperText('Desactivar para pausar el cupón sin eliminarlo')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Descripción')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción Interna')
                            ->rows(3)
                            ->placeholder('Notas internas sobre este cupón...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Code - Searchable, Copyable
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('bold')
                    ->color('primary'),

                // Discount Value - Dynamic format
                TextColumn::make('value')
                    ->label('Descuento')
                    ->formatStateUsing(function (Coupon $record): string {
                        return match ($record->type) {
                            Coupon::TYPE_PERCENT => $record->value.'%',
                            Coupon::TYPE_FIXED => 'L. '.number_format((float) $record->value, 2),
                            Coupon::TYPE_FREE_SHIPPING => 'Envío Gratis',
                            default => (string) $record->value,
                        };
                    })
                    ->description(fn (Coupon $record): ?string => $record->max_discount ? 'Máx: L. '.number_format((float) $record->max_discount, 2) : null
                    ),

                // Usage Counter
                TextColumn::make('usage_count')
                    ->label('Uso')
                    ->formatStateUsing(fn (Coupon $record): string => $record->usage_limit
                            ? "{$record->usage_count} / {$record->usage_limit}"
                            : "{$record->usage_count} / ∞"
                    )
                    ->color(fn (Coupon $record): string => $record->usage_limit && $record->usage_count >= $record->usage_limit
                            ? 'danger'
                            : 'gray'
                    ),

                // Status Badge (computed attribute - explicit getStateUsing for consistency)
                TextColumn::make('status')
                    ->label('Estado')
                    ->getStateUsing(fn (Coupon $record): string => $record->status)
                    ->badge()
                    ->formatStateUsing(fn (Coupon $record): string => $record->status_label)
                    ->color(fn (Coupon $record): string => $record->status_color),

                // Validity Period
                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Tegucigalpa')
                    ->placeholder('Sin expiración')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        Coupon::TYPE_PERCENT => 'Porcentaje',
                        Coupon::TYPE_FIXED => 'Monto Fijo',
                        Coupon::TYPE_FREE_SHIPPING => 'Envío Gratis',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
