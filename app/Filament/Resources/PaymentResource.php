<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PaymentResource - Centro de Comando Financiero
 *
 * Este recurso es un Disparador de Eventos de Negocio:
 * - Aprobar Pago → Activa cuenta PRO + is_active + plan_expires_at
 * - Rechazar Pago → Notifica al usuario con motivo
 *
 * Panel Administrativo con auto-refresh cada 30s.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function getNavigationLabel(): string
    {
        return 'Pagos';
    }

    public static function getModelLabel(): string
    {
        return 'Pago';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pagos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Finanzas';
    }

    /**
     * Badge dinámico: Cuenta pagos pendientes
     */
    public static function getNavigationBadge(): ?string
    {
        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        $count = Payment::withoutGlobalScopes()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Formulario de Solo-Lectura (Vista de Detalles)
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Pago')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->relationship('tenant', 'name')
                            ->label('Negocio')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto')
                            ->prefix('L.')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('bank_reference')
                            ->label('Referencia Bancaria')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'approved' => 'Aprobado',
                                'rejected' => 'Rechazado',
                            ])
                            ->disabled(),

                        Forms\Components\FileUpload::make('proof_image_path')
                            ->label('Comprobante de Pago')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(5120)
                            ->image()
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas Administrativas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Tabla de Datos con Semáforo Visual
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ═══════════════════════════════════════════════════════
                // REFERENCIA BANCARIA (Copyable para verificación)
                // ═══════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('bank_reference')
                    ->label('Referencia')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->copyMessageDuration(1500)
                    ->placeholder('Sin referencia')
                    ->fontFamily('mono')
                    ->size('sm'),

                // ═══════════════════════════════════════════════════════
                // CLIENTE (Con email del propietario para contacto)
                // ═══════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Payment $record): ?string => $record->tenant?->user?->email ?? 'Sin email'
                    ),

                // ═══════════════════════════════════════════════════════
                // MONTO (Formateado como Lempiras)
                // ═══════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('HNL')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),

                // ═══════════════════════════════════════════════════════
                // COMPROBANTE (Imagen thumbnail)
                // ═══════════════════════════════════════════════════════
                Tables\Columns\ImageColumn::make('proof_image_path')
                    ->label('Comprobante')
                    ->circular()
                    ->size(40),

                // ═══════════════════════════════════════════════════════
                // ESTADO (Semáforo con Iconos)
                // ═══════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'pending' => 'heroicon-m-clock',
                        'approved' => 'heroicon-m-check-circle',
                        'rejected' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        default => $state,
                    }),

                // ═══════════════════════════════════════════════════════
                // FECHAS
                // ═══════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reportado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->size('sm')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Procesado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Filtro por estado
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => '🕐 Pendiente',
                        'approved' => '✅ Aprobado',
                        'rejected' => '❌ Rechazado',
                    ]),

                // Filtro de período
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                // ═══════════════════════════════════════════════════════
                // ACCIÓN CRÍTICA: APROBAR PAGO
                // Dispara: status=approved, is_pro=true, is_active=true,
                //          plan_expires_at=+30 días
                // ═══════════════════════════════════════════════════════
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-banknotes')
                    ->modalHeading('¿Confirmar recepción de fondos?')
                    ->modalDescription('Al aprobar, el negocio será elevado a Plan PRO por 30 días.')
                    ->modalSubmitActionLabel('Sí, aprobar pago')
                    ->visible(fn (Payment $record) => $record->status === 'pending')
                    ->action(function (Payment $record) {
                        DB::transaction(function () use ($record) {
                            // 1. Actualizar estado del pago
                            $record->update([
                                'status' => 'approved',
                                'reviewed_at' => now(),
                            ]);

                            // 2. Activar Tenant (Upgrade a PRO)
                            $tenant = $record->tenant;

                            if ($tenant) {
                                // Calcular fecha de expiración
                                $currentExpiry = $tenant->plan_expires_at;

                                // Si ya es PRO y no ha expirado, sumar 30 días
                                if ($tenant->is_pro && $currentExpiry && $currentExpiry->isFuture()) {
                                    $newExpiry = $currentExpiry->addDays(30);
                                } else {
                                    // Nueva suscripción: 30 días desde ahora
                                    $newExpiry = now()->addDays(30);
                                }

                                // Aplicar cambios al Tenant
                                $tenant->update([
                                    'is_pro' => true,
                                    'is_active' => true, // Reactivar si estaba suspendido
                                    'plan_expires_at' => $newExpiry,
                                ]);

                                // 3. Notificar al usuario propietario
                                $user = $tenant->user;
                                if ($user) {
                                    Notification::make()
                                        ->success()
                                        ->title('🎉 ¡Pago Aprobado!')
                                        ->body("Tu Plan PRO para {$tenant->name} está activo hasta el {$newExpiry->format('d/m/Y')}.")
                                        ->sendToDatabase($user);
                                }
                            }
                        });

                        // Notificación al admin
                        Notification::make()
                            ->success()
                            ->title('Pago aprobado')
                            ->body('Cuenta PRO activada correctamente.')
                            ->send();
                    }),

                // ═══════════════════════════════════════════════════════
                // ACCIÓN: RECHAZAR PAGO
                // Solicita motivo y notifica al usuario
                // ═══════════════════════════════════════════════════════
                Tables\Actions\Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo del Rechazo')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ej: Comprobante ilegible, monto incorrecto, etc.'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'notes' => $data['rejection_reason'],
                            'reviewed_at' => now(),
                        ]);

                        // Notificar al usuario
                        $user = $record->tenant?->user;
                        if ($user) {
                            Notification::make()
                                ->danger()
                                ->title('Pago Rechazado')
                                ->body($data['rejection_reason'])
                                ->sendToDatabase($user);
                        }

                        Notification::make()
                            ->warning()
                            ->title('Pago rechazado')
                            ->body('Se notificó al cliente.')
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin pagos pendientes')
            ->emptyStateDescription('Los pagos reportados aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->poll('30s'); // Auto-refresh cada 30 segundos
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    /**
     * Query base: Prioriza pendientes, luego por fecha
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->with(['tenant.user']) // Eager load para performance
            ->orderByRaw("CASE status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                ELSE 4 END");
    }
}
