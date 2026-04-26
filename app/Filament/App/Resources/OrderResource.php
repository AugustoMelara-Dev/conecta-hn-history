<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Filament\App\Resources\OrderResource\Pages;
use App\Models\Location;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\OrderStateService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();

        return $tenant instanceof Tenant
            && app(RubroManifestReaderInterface::class)->hasModule($tenant, 'orders')
            && Gate::allows('viewAny', Order::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return 'Pedidos';
    }

    public static function getModelLabel(): string
    {
        return 'Pedido';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pedidos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $count = Order::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('status', Order::STATUS_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pedidos pendientes de confirmar';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $tenant = static::getTenantRecord();

                if (! $tenant instanceof Tenant) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->where('tenant_id', (string) $tenant->getKey())
                    ->with('location')
                    ->latest();
            })
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('# Orden')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Numero copiado'),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (Order $record): string => $record->customer_phone),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'Pendiente',
                        Order::STATUS_CONFIRMED => 'Confirmado',
                        Order::STATUS_PROCESSING => 'En cocina',
                        Order::STATUS_READY => 'Listo',
                        Order::STATUS_DELIVERED => 'Entregado',
                        Order::STATUS_CANCELLED => 'Cancelado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'warning',
                        Order::STATUS_CONFIRMED => 'info',
                        Order::STATUS_PROCESSING => 'primary',
                        Order::STATUS_READY => 'success',
                        Order::STATUS_DELIVERED => 'gray',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('HNL')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\IconColumn::make('source')
                    ->label('Origen')
                    ->icon(fn (string $state): string => match ($state) {
                        'whatsapp' => 'heroicon-o-chat-bubble-left-ellipsis',
                        'web' => 'heroicon-o-globe-alt',
                        default => 'heroicon-o-device-phone-mobile',
                    })
                    ->color(fn (string $state): string => $state === 'whatsapp' ? 'success' : 'gray')
                    ->tooltip(fn (string $state): string => match ($state) {
                        'whatsapp' => 'Pedido por WhatsApp',
                        'web' => 'Pedido desde web',
                        default => 'Pedido directo',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Hora')
                    ->dateTime('H:i')
                    ->sortable()
                    ->description(fn (Order $record): string => $record->created_at->diffForHumans()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        Order::STATUS_PENDING => 'Pendiente',
                        Order::STATUS_CONFIRMED => 'Confirmado',
                        Order::STATUS_PROCESSING => 'En cocina',
                        Order::STATUS_READY => 'Listo',
                        Order::STATUS_DELIVERED => 'Entregado',
                        Order::STATUS_CANCELLED => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Sucursal')
                    ->options(function (): array {
                        $tenant = static::getTenantRecord();

                        if (! $tenant instanceof Tenant) {
                            return [];
                        }

                        return Location::query()
                            ->where('tenant_id', (string) $tenant->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas las sucursales'),
                Tables\Filters\Filter::make('today')
                    ->label('Solo hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today()))
                    ->default(),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PENDING)
                    ->action(fn (Order $record) => static::transitionOrder($record, Order::STATUS_CONFIRMED, 'Pedido confirmado')),
                Action::make('prepare')
                    ->label('A cocina')
                    ->icon('heroicon-o-fire')
                    ->color('primary')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_CONFIRMED)
                    ->action(fn (Order $record) => static::transitionOrder($record, Order::STATUS_PROCESSING, 'En cocina')),
                Action::make('ready')
                    ->label('Listo')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PROCESSING)
                    ->action(fn (Order $record) => static::transitionOrder($record, Order::STATUS_READY, 'Pedido listo')),
                Action::make('deliver')
                    ->label('Entregar')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_READY)
                    ->action(fn (Order $record) => static::transitionOrder($record, Order::STATUS_DELIVERED, 'Pedido entregado')),
                ViewAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavía no tienes pedidos',
                'description' => 'Cuando un cliente haga una compra o reservación, el pedido aparecerá aquí.',
            ]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('Cliente')
                            ->icon('heroicon-o-user')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('customer_name')
                                    ->label('Nombre')
                                    ->weight('bold')
                                    ->size('lg'),
                                TextEntry::make('customer_phone')
                                    ->label('Telefono')
                                    ->icon('heroicon-o-phone')
                                    ->copyable(),
                                TextEntry::make('customer_email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope')
                                    ->visible(fn (mixed $state): bool => filled($state)),
                                TextEntry::make('delivery_address')
                                    ->label('Direccion')
                                    ->icon('heroicon-o-map-pin')
                                    ->visible(fn (mixed $state): bool => filled($state))
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Detalles del pedido')
                            ->icon('heroicon-o-document-text')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Numero')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        Order::STATUS_PENDING => 'Pendiente',
                                        Order::STATUS_CONFIRMED => 'Confirmado',
                                        Order::STATUS_PROCESSING => 'En cocina',
                                        Order::STATUS_READY => 'Listo',
                                        Order::STATUS_DELIVERED => 'Entregado',
                                        Order::STATUS_CANCELLED => 'Cancelado',
                                        default => $state,
                                    })
                                    ->color(fn (string $state): string => match ($state) {
                                        Order::STATUS_PENDING => 'warning',
                                        Order::STATUS_CONFIRMED => 'info',
                                        Order::STATUS_PROCESSING => 'primary',
                                        Order::STATUS_READY => 'success',
                                        Order::STATUS_DELIVERED => 'gray',
                                        Order::STATUS_CANCELLED => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('payment_method')
                                    ->label('Metodo de pago')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'cash' => 'Efectivo',
                                        'transfer' => 'Transferencia',
                                        'card' => 'Tarjeta',
                                        default => 'Pendiente',
                                    }),
                                TextEntry::make('payment_status')
                                    ->label('Estado de pago')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        default => 'danger',
                                    }),
                                TextEntry::make('customer_notes')
                                    ->label('Notas del cliente')
                                    ->visible(fn (mixed $state): bool => filled($state))
                                    ->columnSpanFull()
                                    ->prose(),
                                TextEntry::make('created_at')
                                    ->label('Fecha/Hora')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                        Section::make('Ticket de orden')
                            ->icon('heroicon-o-receipt-percent')
                            ->columnSpan(1)
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('product_name')
                                            ->label('')
                                            ->weight('bold')
                                            ->size('lg'),
                                        TextEntry::make('quantity')
                                            ->label('')
                                            ->formatStateUsing(fn (int $state): string => "x{$state}")
                                            ->badge()
                                            ->color('primary'),
                                        TextEntry::make('total_price')
                                            ->label('')
                                            ->money('HNL')
                                            ->weight('bold'),
                                        TextEntry::make('selected_options')
                                            ->label('')
                                            ->formatStateUsing(function (mixed $state): string {
                                                if (blank($state)) {
                                                    return '';
                                                }

                                                $options = is_array($state) ? $state : json_decode((string) $state, true);

                                                if (! is_array($options) || $options === []) {
                                                    return '';
                                                }

                                                return collect($options)
                                                    ->groupBy('group')
                                                    ->map(function ($items, $group): string {
                                                        $names = $items->pluck('option')->implode(', ');

                                                        return "{$group}: {$names}";
                                                    })
                                                    ->implode(' | ');
                                            })
                                            ->visible(fn (mixed $state): bool => filled($state))
                                            ->color('gray')
                                            ->size('sm'),
                                    ])
                                    ->columns(3),
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('subtotal')
                                            ->label('Subtotal')
                                            ->money('HNL'),
                                        TextEntry::make('discount')
                                            ->label('Descuento')
                                            ->money('HNL')
                                            ->visible(fn (float|int|string|null $state): bool => (float) $state > 0),
                                        TextEntry::make('delivery_fee')
                                            ->label('Envio')
                                            ->money('HNL')
                                            ->visible(fn (float|int|string|null $state): bool => (float) $state > 0),
                                        TextEntry::make('total')
                                            ->label('TOTAL')
                                            ->money('HNL')
                                            ->weight('bold')
                                            ->size('lg')
                                            ->color('success'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    protected static function transitionOrder(Order $order, string $status, string $title): void
    {
        try {
            app(OrderStateService::class)->transition($order, $status);

            Notification::make()
                ->title($title)
                ->success()
                ->send();
        } catch (\Exception $exception) {
            Notification::make()
                ->title('Error')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
