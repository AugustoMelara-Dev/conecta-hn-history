<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class PendingPaymentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pagos Pendientes de Revision';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /**
     * Control de Visibilidad por Rol
     * Solo super_admin y auditor pueden ver pagos pendientes
     * El rol 'soporte' NO debe ver informacion financiera
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Soporte no puede ver widgets financieros
        if ($user->hasRole('soporte')) {
            return false;
        }

        // super_admin or auditor with whitelist verification can view
        $superAdmins = config('auth.super_admins', []);
        $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);

        return $user->hasRole(['super_admin', 'auditor'])
            || ($user->hasRole('super_admin') && $isWhitelisted);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->where('status', 'pending')
                    ->with('tenant')
            )
            ->defaultSort('created_at', 'asc')
            ->emptyStateHeading('Todo al dia!')
            ->emptyStateDescription('No hay pagos pendientes de revision.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('tenant.name')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('HNL')
                    ->sortable()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Fecha de Solicitud')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('waiting_time')
                    ->label('Espera')
                    ->state(fn (Payment $record): string => $record->created_at->diffForHumans()
                    )
                    ->badge()
                    ->color(fn (Payment $record): string => $record->created_at->diffInHours() > 24 ? 'danger' : 'warning'
                    ),

                ImageColumn::make('proof_image_path')
                    ->label('Comprobante')
                    ->circular()
                    ->size(40),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Pago')
                    ->modalDescription(fn (Payment $record): string => 'Confirmar pago de L. '.number_format($record->amount, 2).
                        ' del negocio '.$record->tenant->name.'?'
                    )
                    ->action(function (Payment $record) {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'status' => 'approved',
                                'reviewed_at' => now(),
                            ]);

                            $record->tenant->update([
                                'is_pro' => true,
                                'plan_expires_at' => now()->addMonth(),
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pago Aprobado')
                            ->body('El negocio '.$record->tenant->name.' ahora es PRO.')
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Pago')
                    ->form([
                        Textarea::make('notes')
                            ->label('Motivo del Rechazo')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Payment $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_at' => now(),
                            'notes' => $data['notes'],
                        ]);

                        Notification::make()
                            ->warning()
                            ->title('Pago Rechazado')
                            ->send();
                    }),

                Action::make('view_proof')
                    ->label('Ver')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->url(fn (Payment $record): string => asset('storage/'.$record->proof_image_path)
                    )
                    ->openUrlInNewTab(),
            ])
            ->poll('30s');
    }
}
