<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Order::class);
    }

    public static function shouldRegisterNavigation(): bool
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

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    // ═══ BADGE DE NAVEGACIÓN: Pedidos Pendientes ═══
    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return null;
        }

        $count = static::getModel()::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
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
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('tenant_id', Filament::getTenant()->id)
                ->latest())
            ->columns([
                // ═══ NÚMERO DE ORDEN ═══
                Tables\Columns\TextColumn::make('order_number')
                    ->label('# Orden')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Número copiado'),

                // ═══ CLIENTE ═══
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (Order $record): string => $record->customer_phone),

                // ═══ ESTADO CON COLORES ═══
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ Pendiente',
                        'confirmed' => '✓ Confirmado',
                        'preparing' => '👨‍🍳 En Cocina',
                        'ready' => '✅ Listo',
                        'delivered' => '📦 Entregado',
                        'cancelled' => '❌ Cancelado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'preparing' => 'primary',
                        'ready' => 'success',
                        'delivered' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                // ═══ TOTAL ═══
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('HNL')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                // ═══ ORIGEN (WhatsApp) ═══
                Tables\Columns\IconColumn::make('source')
                    ->label('Origen')
                    ->icon(fn (string $state): string => match ($state) {
                        'whatsapp' => 'heroicon-o-chat-bubble-left-ellipsis',
                        'web' => 'heroicon-o-globe-alt',
                        default => 'heroicon-o-device-phone-mobile',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'whatsapp' => 'Pedido por WhatsApp',
                        'web' => 'Pedido desde Web',
                        default => 'Pedido Directo',
                    }),

                // ═══ ITEMS COUNT ═══
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                // ═══ TIEMPO ═══
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
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmado',
                        'preparing' => 'En Cocina',
                        'ready' => 'Listo',
                        'delivered' => 'Entregado',
                        'cancelled' => 'Cancelado',
                    ]),
                Tables\Filters\Filter::make('today')
                    ->label('Solo Hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today()))
                    ->default(),
            ])
            ->actions([
                // ═══ ACCIONES RÁPIDAS DE ESTADO ═══
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status === 'pending')
                    ->action(function (Order $record) {
                        try {
                            app(\App\Services\OrderStateService::class)->transition($record, 'confirmed');
                            \Filament\Notifications\Notification::make()
                                ->title('Pedido Confirmado')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('prepare')
                    ->label('A Cocina')
                    ->icon('heroicon-o-fire')
                    ->color('primary')
                    ->visible(fn (Order $record): bool => $record->status === 'confirmed')
                    ->action(function (Order $record) {
                        try {
                            app(\App\Services\OrderStateService::class)->transition($record, 'processing');
                            \Filament\Notifications\Notification::make()
                                ->title('En Cocina')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('ready')
                    ->label('Listo')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->status === 'processing')
                    ->action(function (Order $record) {
                        try {
                            app(\App\Services\OrderStateService::class)->transition($record, 'ready');
                            \Filament\Notifications\Notification::make()
                                ->title('Pedido Listo')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('deliver')
                    ->label('Entregar')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status === 'ready')
                    ->action(function (Order $record) {
                        try {
                            app(\App\Services\OrderStateService::class)->transition($record, 'delivered');
                            \Filament\Notifications\Notification::make()
                                ->title('Pedido Entregado')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                ViewAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            // ->poll('30s') // Auto-refresh
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin pedidos')
            ->emptyStateDescription('Los pedidos de tus clientes aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    // ═══ VISTA DETALLADA CON INFOLIST ═══
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Components\Grid::make(3)
                    ->schema([
                        // ═══ COLUMNA 1: CLIENTE ═══
                        Infolists\Components\Section::make('Cliente')
                            ->icon('heroicon-o-user')
                            ->columnSpan(1)
                            ->schema([
                                Infolists\Components\TextEntry::make('customer_name')
                                    ->label('Nombre')
                                    ->weight('bold')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('customer_phone')
                                    ->label('Teléfono')
                                    ->icon('heroicon-o-phone')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('customer_email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope')
                                    ->visible(fn ($state) => ! empty($state)),

                                Infolists\Components\TextEntry::make('delivery_address')
                                    ->label('Dirección')
                                    ->icon('heroicon-o-map-pin')
                                    ->visible(fn ($state) => ! empty($state))
                                    ->columnSpanFull(),

                                Infolists\Components\Actions::make([
                                    Infolists\Components\Actions\Action::make('whatsapp')
                                        ->label('Abrir WhatsApp')
                                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                        ->color('success')
                                        ->url(fn (Order $record): string => 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->customer_phone)
                                        )
                                        ->openUrlInNewTab(),
                                ]),
                            ]),

                        // ═══ COLUMNA 2: DETALLES ═══
                        Infolists\Components\Section::make('Detalles del Pedido')
                            ->icon('heroicon-o-document-text')
                            ->columnSpan(1)
                            ->schema([
                                Infolists\Components\TextEntry::make('order_number')
                                    ->label('Número')
                                    ->weight('bold')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending' => 'Pendiente',
                                        'confirmed' => 'Confirmado',
                                        'preparing' => 'En Cocina',
                                        'ready' => 'Listo',
                                        'delivered' => 'Entregado',
                                        'cancelled' => 'Cancelado',
                                        default => $state,
                                    })
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'preparing' => 'primary',
                                        'ready' => 'success',
                                        'delivered' => 'gray',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Método de Pago')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'cash' => '💵 Efectivo',
                                        'transfer' => '🏦 Transferencia',
                                        'card' => '💳 Tarjeta',
                                        default => '⏳ Pendiente',
                                    }),

                                Infolists\Components\TextEntry::make('payment_status')
                                    ->label('Estado de Pago')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        default => 'danger',
                                    }),

                                Infolists\Components\TextEntry::make('customer_notes')
                                    ->label('Notas del Cliente')
                                    ->visible(fn ($state) => ! empty($state))
                                    ->columnSpanFull()
                                    ->prose(),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Fecha/Hora')
                                    ->dateTime('d/m/Y H:i'),
                            ]),

                        // ═══ COLUMNA 3: TICKET ═══
                        Infolists\Components\Section::make('Ticket de Orden')
                            ->icon('heroicon-o-receipt-percent')
                            ->columnSpan(1)
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('product_name')
                                            ->label('')
                                            ->weight('bold')
                                            ->size('lg'),

                                        Infolists\Components\TextEntry::make('quantity')
                                            ->label('')
                                            ->formatStateUsing(fn (int $state): string => "x{$state}")
                                            ->badge()
                                            ->color('primary'),

                                        Infolists\Components\TextEntry::make('total_price')
                                            ->label('')
                                            ->money('HNL')
                                            ->weight('bold'),

                                        // ═══ OPCIONES SELECCIONADAS (JSON) ═══
                                        Infolists\Components\TextEntry::make('selected_options')
                                            ->label('')
                                            ->formatStateUsing(function ($state): string {
                                                if (empty($state)) {
                                                    return '';
                                                }

                                                $options = is_array($state) ? $state : json_decode($state, true);
                                                if (empty($options)) {
                                                    return '';
                                                }

                                                $formatted = collect($options)
                                                    ->groupBy('group')
                                                    ->map(function ($items, $group) {
                                                        $names = $items->pluck('option')->implode(', ');

                                                        return "{$group}: {$names}";
                                                    })
                                                    ->implode(' | ');

                                                return $formatted;
                                            })
                                            ->visible(fn ($state) => ! empty($state))
                                            ->color('gray')
                                            ->size('sm'),
                                    ])
                                    ->columns(3),

                                Infolists\Components\Grid::make(2)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('subtotal')
                                            ->label('Subtotal')
                                            ->money('HNL'),
                                        Infolists\Components\TextEntry::make('discount')
                                            ->label('Descuento')
                                            ->money('HNL')
                                            ->visible(fn ($state) => $state > 0),
                                        Infolists\Components\TextEntry::make('delivery_fee')
                                            ->label('Envío')
                                            ->money('HNL')
                                            ->visible(fn ($state) => $state > 0),
                                        Infolists\Components\TextEntry::make('total')
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
        // No usamos form para edición, solo infolist para vista
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
}
