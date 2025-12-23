<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * OrderResource - Order Management in Filament Admin
 * 
 * KANBAN-STYLE WORKFLOW:
 * Orders are displayed with visual status indicators and quick actions
 * for transitioning through the workflow stages.
 * 
 * Status Flow: Pending → Confirmed → Processing → Ready → Delivered
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    
    protected static ?string $navigationLabel = 'Pedidos';
    
    protected static ?string $modelLabel = 'Pedido';
    
    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?string $navigationGroup = 'Ventas';
    
    protected static ?int $navigationSort = 1;

    // Show badge with pending orders count
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Pedido')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Número de Orden')
                            ->disabled()
                            ->columnSpan(1),
                        
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(Order::KANBAN_STATUSES)
                            ->required()
                            ->native(false)
                            ->columnSpan(1),
                        
                        Forms\Components\Select::make('delivery_type')
                            ->label('Tipo de Entrega')
                            ->options([
                                'pickup' => 'Recoger en tienda',
                                'delivery' => 'Entrega a domicilio',
                            ])
                            ->required()
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Datos del Cliente')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Teléfono')
                            ->required()
                            ->tel()
                            ->maxLength(20),
                        
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('payment_method')
                            ->label('Método de Pago')
                            ->options([
                                'pending' => 'Pendiente',
                                'cash' => 'Efectivo',
                                'card' => 'Tarjeta',
                                'transfer' => 'Transferencia',
                            ]),
                        
                        Forms\Components\Textarea::make('customer_address')
                            ->label('Dirección')
                            ->rows(2)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('customer_notes')
                            ->label('Notas del Cliente')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Totales')
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('L.')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('delivery_fee')
                            ->label('Envío')
                            ->numeric()
                            ->prefix('L.')
                            ->default(0),
                        
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Descuento')
                            ->numeric()
                            ->prefix('L.')
                            ->default(0),
                        
                        Forms\Components\TextInput::make('total')
                            ->label('Total')
                            ->numeric()
                            ->prefix('L.')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Orden')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (Order $record): string => $record->customer_phone),
                
                Tables\Columns\TextColumn::make('items_summary')
                    ->label('Productos')
                    ->wrap()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'L. ' . number_format($state, 2))
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'confirmed',
                        'primary' => 'processing',
                        'success' => 'ready',
                        'gray' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-check-circle' => 'confirmed',
                        'heroicon-o-fire' => 'processing',
                        'heroicon-o-gift' => 'ready',
                        'heroicon-o-check' => 'delivered',
                        'heroicon-o-x-circle' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => Order::KANBAN_STATUSES[$state] ?? ucfirst($state)),
                
                Tables\Columns\TextColumn::make('delivery_type')
                    ->label('Entrega')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pickup' => 'gray',
                        'delivery' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state === 'delivery' ? 'Domicilio' : 'Recoger'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recibido')
                    ->since()
                    ->sortable()
                    ->color(function (Order $record) {
                        $minutes = $record->created_at->diffInMinutes(now());
                        if ($minutes > 60) return 'danger';
                        if ($minutes > 30) return 'warning';
                        return 'success';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Order::KANBAN_STATUSES)
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('delivery_type')
                    ->label('Tipo de Entrega')
                    ->options([
                        'pickup' => 'Recoger',
                        'delivery' => 'Domicilio',
                    ]),
                
                Tables\Filters\Filter::make('today')
                    ->label('Solo Hoy')
                    ->query(fn (Builder $query) => $query->whereDate('created_at', today()))
                    ->toggle(),
                
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // Quick status transitions
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('confirm')
                        ->label('Confirmar')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->status === 'pending')
                        ->action(fn (Order $record) => $record->transitionTo('confirmed')),
                    
                    Tables\Actions\Action::make('process')
                        ->label('En Preparación')
                        ->icon('heroicon-o-fire')
                        ->color('warning')
                        ->visible(fn (Order $record) => in_array($record->status, ['pending', 'confirmed']))
                        ->action(fn (Order $record) => $record->transitionTo('processing')),
                    
                    Tables\Actions\Action::make('ready')
                        ->label('Listo')
                        ->icon('heroicon-o-gift')
                        ->color('success')
                        ->visible(fn (Order $record) => $record->status === 'processing')
                        ->action(fn (Order $record) => $record->transitionTo('ready')),
                    
                    Tables\Actions\Action::make('deliver')
                        ->label('Entregado')
                        ->icon('heroicon-o-check')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->status === 'ready')
                        ->action(fn (Order $record) => $record->transitionTo('delivered')),
                    
                    Tables\Actions\Action::make('cancel')
                        ->label('Cancelar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => !in_array($record->status, ['delivered', 'cancelled']))
                        ->action(fn (Order $record) => $record->transitionTo('cancelled')),
                ]),
                
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Order $record) => $record->getWhatsAppUrl())
                    ->openUrlInNewTab(),
                
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    /**
     * SECURITY: Redundant tenant filtering for Defense in Depth
     * 
     * Even though BelongsToTenant trait applies TenantScope globally,
     * we explicitly filter here as a second line of defense.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        }
        
        return $query;
    }
}
