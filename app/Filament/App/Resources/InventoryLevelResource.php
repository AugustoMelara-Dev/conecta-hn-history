<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\InventoryLevelResource\Pages;
use App\Filament\Traits\ScopesToTenant;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getNavigationLabel(): string
    {
        return 'Existencias';
    }

    public static function getModelLabel(): string
    {
        return 'Existencia';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Existencias';
    }

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();

        return $tenant instanceof Tenant
            && $tenant->hasCapability(Capability::INVENTORY_TRACKING);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
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
                    ->label('Producto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Sucursal')
                    ->searchable(),
                Tables\Columns\TextColumn::make('on_hand')
                    ->label('Disponible')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved')
                    ->label('Reservado')
                    ->numeric(decimalPlaces: 3),
                Tables\Columns\TextColumn::make('low_stock_threshold')
                    ->label('Minimo')
                    ->numeric(decimalPlaces: 3)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('adjust_stock')
                    ->label('Ajustar stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (InventoryLevel $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->form([
                        Forms\Components\TextInput::make('quantity_delta')
                            ->label('Cantidad a ajustar')
                            ->numeric()
                            ->required()
                            ->helperText('Usa números positivos para sumar y negativos para restar.'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Motivo')
                            ->required(),
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
                Action::make('report_loss')
                    ->label('Registrar merma')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (InventoryLevel $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->form([
                        Forms\Components\TextInput::make('loss_quantity')
                            ->label('Cantidad perdida')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                        Forms\Components\Textarea::make('notes')
                            ->label('Motivo')
                            ->required(),
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
            ->bulkActions([])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavía no hay existencias registradas',
                'description' => 'El inventario aparecerá aquí cuando tus productos tengan entradas, ventas o ajustes.',
            ]));
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

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
