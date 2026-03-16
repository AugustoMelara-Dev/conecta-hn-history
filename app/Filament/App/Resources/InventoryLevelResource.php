<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\Inventory\Enums\MovementType;
use App\Filament\App\Resources\InventoryLevelResource\Pages;
use App\Filament\Traits\ScopesToTenant;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class InventoryLevelResource extends Resource
{
    use ScopesToTenant;

    protected static ?string $model = InventoryLevel::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventory Levels';
    }

    public static function getModelLabel(): string
    {
        return 'Inventory Level';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Inventory Levels';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable(),
                Tables\Columns\TextColumn::make('on_hand')
                    ->label('On Hand')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved')
                    ->label('Reserved')
                    ->numeric(decimalPlaces: 3),
                Tables\Columns\TextColumn::make('low_stock_threshold')
                    ->label('Low Stock Threshold')
                    ->numeric(decimalPlaces: 3)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (InventoryLevel $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->form([
                        Forms\Components\TextInput::make('quantity_delta')
                            ->label('Adjustment Quantity')
                            ->numeric()
                            ->required()
                            ->helperText('Use positive values to add, negative to subtract.'),
                        Forms\Components\Textarea::make('notes')->required(),
                    ])
                    ->action(function (InventoryLevel $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $level = InventoryLevel::query()
                                ->where('id', $record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $delta = round((float) $data['quantity_delta'], 3);
                            $level->on_hand = round((float) $level->on_hand + $delta, 3);
                            $level->save();

                            InventoryMovement::create([
                                'tenant_id' => $level->tenant_id,
                                'location_id' => $level->location_id,
                                'product_id' => $level->product_id,
                                'user_id' => auth()->id(),
                                'quantity_delta' => $delta,
                                'type' => MovementType::MANUAL_ADJUSTMENT,
                                'notes' => $data['notes'],
                            ]);
                        });
                    }),
                Tables\Actions\Action::make('report_loss')
                    ->label('Report Loss')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (InventoryLevel $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->form([
                        Forms\Components\TextInput::make('loss_quantity')
                            ->label('Quantity Lost')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                        Forms\Components\Textarea::make('notes')->required(),
                    ])
                    ->action(function (InventoryLevel $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $level = InventoryLevel::query()
                                ->where('id', $record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $loss = round(abs((float) $data['loss_quantity']), 3);
                            $level->on_hand = round((float) $level->on_hand - $loss, 3);
                            $level->save();

                            InventoryMovement::create([
                                'tenant_id' => $level->tenant_id,
                                'location_id' => $level->location_id,
                                'product_id' => $level->product_id,
                                'user_id' => auth()->id(),
                                'quantity_delta' => -$loss,
                                'type' => MovementType::LOSS,
                                'notes' => $data['notes'],
                            ]);
                        });
                    }),
            ])
            ->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLevels::route('/'),
        ];
    }
}
