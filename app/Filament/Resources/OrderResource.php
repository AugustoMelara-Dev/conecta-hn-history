<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontFamily;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Services\OrderStateService;

/**
 * OrderResource - Forensic Immutable Viewer & Workflow Command Center
 *
 * CONTEXT: FASE 3 - ADMINISTRATIVE SAAS
 * ROLE: Immutable Transaction Viewer
 *
 * DESIGN DECISIONS:
 * 1. IMMUTABILITY: Edit/Delete actions are strictly prohibited to preserve audit trails.
 * 2. CLARITY: Monospaced fonts for IDs, semantic colors for status.
 * 3. SECURITY: Anti-XSS rendering for user-generated content (modifiers).
 * 4. WORKFLOW: Simplified "Pending -> Completed" flow for rapid processing.
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

    // ═══════════════════════════════════════════════════════════════════════
    // PAGE REGISTRATION (Restricted to Index & View)
    // ═══════════════════════════════════════════════════════════════════════
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            // 'create' => Pages\CreateOrder::route('/create'), // OMITTED: Orders originate from Frontend
            'view' => Pages\ViewOrder::route('/{record}'),
            // 'edit' => Pages\EditOrder::route('/{record}/edit'), // OMITTED: Immutability Enforcement
        ];
    }

    /**
     * FORM DEFINITION
     * Note: Even though we don't allow editing, this definition is kept distinct
     * from the Infolist for potential future use or read-only forms.
     * However, the primary detail view is handled by infolist().
     */
    public static function form(Form $form): Form
    {
        return $form->schema([]); // Empty form as editing is disabled
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TABLE: COMMAND CENTER
    // ═══════════════════════════════════════════════════════════════════════
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // IDENTIFIER: Monospaced for technical legibility
                Tables\Columns\TextColumn::make('order_number')
                    ->label('N° Orden')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->copyable(),

                // CLIENT: Name + Phone (Metadata)
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->description(fn (Order $record): string => $record->customer_phone ?? '')
                    ->searchable(['customer_name', 'customer_phone'], isIndividual: true)
                    ->weight('medium'),

                // STATUS: Semantic Badge Logic
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->colors([
                        'warning' => 'pending',   // Advertencia (Requiere atención)
                        'success' => 'delivered', // Éxito (Completado)     - Mapped from 'completed' requirement
                        'danger'  => 'cancelled', // Peligro (Fallido)
                        'primary' => 'processing',
                        'info'    => 'confirmed',
                        'gray'    => 'ready',
                    ]),

                // TOTAL: Right-aligned monetary format
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('HNL')
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recibido')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Default filters can remain or be customized.
                // Keeping it simple for the "Command Center" feel.
                Tables\Filters\SelectFilter::make('status')
                    ->options(Order::KANBAN_STATUSES) // Reuse existing constants
                    ->label('Estado'),
            ])
            ->actions([
                // ═══════════════════════════════════════════════════════════════
                // ACTION: VIEW
                // ═══════════════════════════════════════════════════════════════
                Tables\Actions\ViewAction::make(),

                // ═══════════════════════════════════════════════════════════════
                // ACTION: WHATSAPP CONTACT (Deep Link with Sanitization)
                // ═══════════════════════════════════════════════════════════════
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(function (Order $record) {
                        // SANITIZATION: Remove all non-digits
                        $phone = preg_replace('/\D/', '', $record->customer_phone);
                        
                        // Honduras Localization: Ensure 504 prefix entry
                        if (strlen($phone) === 8) {
                           $phone = '504' . $phone; 
                        }

                        return "https://wa.me/{$phone}";
                    })
                    ->visible(fn (Order $record) => !empty($record->customer_phone))
                    ->openUrlInNewTab(),

                // ═══════════════════════════════════════════════════════════════
                // ACTION: COMPLETE (Workflow Transition)
                // ═══════════════════════════════════════════════════════════════
                Tables\Actions\Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    // VISIBILITY: Only for pending items
                    ->visible(fn (Order $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('¿Completar Pedido?')
                    ->modalDescription('El pedido pasará a estado completado/entregado. Esta acción registrará el cambio en el historial.')
                    ->action(function (Order $record) {
                        // LOGIC: Delegate to Service for side-effects (inventory, logs)
                        try {
                            // Mapping 'Complete' requirement to 'delivered' status to maintain system integrity.
                            // If strict 'completed' status is needed, Order::STATUS_COMPLETED must be defined.
                            // Assuming 'delivered' is the logical equivalent in current architecture.
                            app(OrderStateService::class)->transition($record, 'delivered');

                            Notification::make()
                                ->title('Pedido Completado')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al completar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            // IMMUTABILITY: NO BULK ACTIONS
            ->bulkActions([]); 
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INFOLIST: FORENSIC DECODER
    // ═══════════════════════════════════════════════════════════════════════
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle del Pedido')
                    ->schema([
                        // REPEATABLE ENTRY: Decodes 'items' relationship/JSON
                        RepeatableEntry::make('items')
                            ->label('Productos')
                            ->schema([
                                Grid::make(4)->schema([
                                    // COLUMN 1: Quantity
                                    TextEntry::make('quantity')
                                        ->label('Cant.')
                                        ->weight('bold'),

                                    // COLUMN 2 & 3: Product + Modifiers (HTML)
                                    TextEntry::make('product_name') // Using name from OrderItem
                                        ->label('Producto')
                                        ->formatStateUsing(function ($state, $record) {
                                            $baseName = e($state);
                                            $options = $record->options ?? []; // Nested JSON 'options'

                                            if (empty($options)) {
                                                return $baseName;
                                            }

                                            // ALGORITHM: Anti-XSS Render Loop
                                            $badges = collect($options)->map(function ($opt) {
                                                // SECURITY CRITICAL: Escape user input
                                                $safeOption = e($opt['option'] ?? 'N/A');
                                                $safePrice = isset($opt['price']) && $opt['price'] > 0 
                                                    ? ' (+L.'.number_format($opt['price'], 2).')' 
                                                    : '';
                                                
                                                return "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 ml-1'>{$safeOption}{$safePrice}</span>";
                                            })->join(' ');

                                            return $baseName . "<br>" . $badges;
                                        })
                                        ->html() // Enable HTML rendering for badges
                                        ->columnSpan(2),

                                    // COLUMN 4: Unit Price
                                    TextEntry::make('price')
                                        ->label('Precio Unit.')
                                        ->money('HNL')
                                        ->alignEnd(),
                                ]),
                            ])
                            ->columnSpanFull()
                            ->grid(1), // Display as a list
                    ]),

                // Totals Section
                Section::make('Resumen Financiero')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')->money('HNL'),
                        TextEntry::make('delivery_fee')->label('Envío')->money('HNL'),
                        TextEntry::make('total')->money('HNL')->weight('bold')->size(TextEntry\TextEntrySize::Large),
                    ]),
                
                // Customer Section
                Section::make('Datos del Cliente')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_name')->label('Nombre'),
                        TextEntry::make('customer_phone')->label('Teléfono'),
                        TextEntry::make('customer_address')->label('Dirección')->columnSpanFull(),
                        TextEntry::make('customer_notes')->label('Notas')->columnSpanFull(),
                    ]),
            ]);
    }

    use \App\Filament\Traits\ScopesToTenant;

    public static function getRelations(): array
    {
        return [];
    }
}
