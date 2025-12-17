<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-currency-dollar';
    }

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

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion del Pago')
                    ->schema([
                        Select::make('tenant_id')
                            ->label('Negocio')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('amount')
                            ->label('Monto (Lempiras)')
                            ->numeric()
                            ->prefix('L.')
                            ->required(),

                        TextInput::make('bank_reference')
                            ->label('Referencia Bancaria')
                            ->placeholder('Numero de transaccion del banco'),

                        FileUpload::make('proof_image_path')
                            ->label('Comprobante de Pago')
                            ->image()
                            ->directory('payments/proofs')
                            ->imageEditor()
                            ->required(),

                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'approved' => 'Aprobado',
                                'rejected' => 'Rechazado',
                            ])
                            ->default('pending')
                            ->required()
                            ->disabled(fn ($record) => $record && $record->status !== 'pending'),

                        Textarea::make('notes')
                            ->label('Notas Internas')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\ImageColumn::make('proof_image_path')
                    ->label('Comprobante')
                    ->circular()
                    ->size(50),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('HNL')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('bank_reference')
                    ->label('Ref. Bancaria')
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Revisado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Pendiente')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ]),

                Tables\Filters\Filter::make('pending_priority')
                    ->label('Prioridad (Pendientes)')
                    ->query(fn ($query) => $query->where('status', 'pending'))
                    ->toggle(),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Revisar'),
                
                // ═══════════════════════════════════════════════════════════
                // ACCION CRITICA: CONFIRMAR PAGO (Transaccion Atomica)
                // ═══════════════════════════════════════════════════════════
                Action::make('confirmPayment')
                    ->label('Confirmar Pago')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Pago y Activar PRO')
                    ->modalDescription(fn (Payment $record): string => 
                        "Confirmar pago de L. " . number_format($record->amount, 2) . 
                        " del negocio '" . $record->tenant->name . "'?\n\n" .
                        "Esto activara el Plan PRO y enviara confirmacion al cliente."
                    )
                    ->modalSubmitActionLabel('Confirmar y Activar PRO')
                    ->visible(fn (Payment $record): bool => $record->status === 'pending')
                    ->action(function (Payment $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // 1. Actualizar estado del pago
                                $record->update([
                                    'status' => 'approved',
                                    'reviewed_at' => now(),
                                ]);

                                // 2. Calcular fecha de vencimiento inteligente
                                $tenant = $record->tenant;
                                $currentExpiry = $tenant->plan_expires_at;
                                
                                if ($currentExpiry && $currentExpiry->isFuture()) {
                                    // Plan activo: sumar 30 dias a la fecha existente
                                    $newExpiry = $currentExpiry->addDays(30);
                                } else {
                                    // Plan vencido o nuevo: 30 dias desde hoy
                                    $newExpiry = now()->addDays(30);
                                }

                                // 3. Activar PRO en el tenant
                                $tenant->update([
                                    'is_pro' => true,
                                    'plan_expires_at' => $newExpiry,
                                    'is_active' => true,
                                ]);

                                // 4. Enviar email de confirmacion
                                try {
                                    Mail::to($tenant->user->email)->send(
                                        new \App\Mail\PaymentConfirmedMail($record, $tenant)
                                    );
                                } catch (\Exception $e) {
                                    \Log::warning('No se pudo enviar email de confirmacion: ' . $e->getMessage());
                                }
                            });

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Pago Confirmado')
                                ->body("El negocio '{$record->tenant->name}' ahora es PRO hasta " . 
                                    $record->tenant->fresh()->plan_expires_at->format('d/m/Y'))
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al procesar pago')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // ═══════════════════════════════════════════════════════════
                // ACCION DE RECHAZO CON FEEDBACK LOOP
                // ═══════════════════════════════════════════════════════════
                Action::make('rejectPayment')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Pago')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Motivo del Rechazo')
                            ->placeholder('Explique por que se rechaza este comprobante...')
                            ->required()
                            ->rows(4)
                            ->helperText('Este mensaje sera enviado al cliente por correo.'),
                    ])
                    ->visible(fn (Payment $record): bool => $record->status === 'pending')
                    ->action(function (Payment $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_at' => now(),
                            'notes' => $data['rejection_reason'],
                        ]);

                        // Enviar email de rechazo
                        try {
                            Mail::to($record->tenant->user->email)->send(
                                new \App\Mail\PaymentRejectedMail($record, $data['rejection_reason'])
                            );
                        } catch (\Exception $e) {
                            \Log::warning('No se pudo enviar email de rechazo: ' . $e->getMessage());
                        }

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Pago Rechazado')
                            ->body('El cliente ha sido notificado del rechazo.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'view' => ViewPayment::route('/{record}'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }
}
