<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ═══════════════════════════════════════════════════════════════
            // ACCION HEADER: CONFIRMAR PAGO
            // ═══════════════════════════════════════════════════════════════
            Action::make('confirmPayment')
                ->label('Confirmar Pago')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->size('lg')
                ->requiresConfirmation()
                ->modalHeading('Confirmar Pago y Activar PRO')
                ->modalDescription(fn () => 'Confirmar pago de L. '.number_format($this->record->amount, 2).
                    " del negocio '".$this->record->tenant->name."'?\n\n".
                    'Esto activara el Plan PRO y enviara confirmacion al cliente.'
                )
                ->modalSubmitActionLabel('Confirmar y Activar PRO')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->action(function () {
                    try {
                        DB::transaction(function () {
                            $record = $this->record;

                            $record->update([
                                'status' => 'approved',
                                'reviewed_at' => now(),
                            ]);

                            $tenant = $record->tenant;
                            $currentExpiry = $tenant->plan_expires_at;

                            if ($currentExpiry && $currentExpiry->isFuture()) {
                                $newExpiry = $currentExpiry->addDays(30);
                            } else {
                                $newExpiry = now()->addDays(30);
                            }

                            $tenant->update([
                                'is_pro' => true,
                                'plan_expires_at' => $newExpiry,
                                'is_active' => true,
                            ]);

                            try {
                                Mail::to($tenant->user->email)->send(
                                    new \App\Mail\PaymentConfirmedMail($record, $tenant)
                                );
                            } catch (\Exception $e) {
                                \Log::warning('Email error: '.$e->getMessage());
                            }
                        });

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Pago Confirmado')
                            ->body('Plan PRO activado hasta '.
                                $this->record->tenant->fresh()->plan_expires_at->format('d/m/Y'))
                            ->send();

                        $this->redirect(PaymentResource::getUrl('index'));

                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // ═══════════════════════════════════════════════════════════════
            // ACCION HEADER: RECHAZAR PAGO
            // ═══════════════════════════════════════════════════════════════
            Action::make('rejectPayment')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->size('lg')
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
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'rejected',
                        'reviewed_at' => now(),
                        'notes' => $data['rejection_reason'],
                    ]);

                    try {
                        Mail::to($this->record->tenant->user->email)->send(
                            new \App\Mail\PaymentRejectedMail($this->record, $data['rejection_reason'])
                        );
                    } catch (\Exception $e) {
                        \Log::warning('Email error: '.$e->getMessage());
                    }

                    \Filament\Notifications\Notification::make()
                        ->warning()
                        ->title('Pago Rechazado')
                        ->body('El cliente ha sido notificado.')
                        ->send();

                    $this->redirect(PaymentResource::getUrl('index'));
                }),
        ];
    }
}
