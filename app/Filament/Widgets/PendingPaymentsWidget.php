<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
        if (! $user instanceof User) {
            return false;
        }

        if (self::hasAnyLegacyRole($user, ['soporte'])) {
            return false;
        }

        $superAdmins = config('auth.super_admins', []);
        $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);

        return $user->isSuperAdmin()
            || self::hasAnyLegacyRole($user, ['auditor'])
            || ($isWhitelisted && self::hasAnyLegacyRole($user, ['super_admin']));
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
                    ->state(fn (Payment $record): string => $record->created_at->diffForHumans())
                    ->badge()
                    ->color(fn (Payment $record): string => $record->created_at->diffInHours() > 24 ? 'danger' : 'warning'),

                ImageColumn::make('proof_path')
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
                    ->modalDescription(function (Payment $record): string {
                        $tenant = $record->tenant;
                        $tenantName = $tenant instanceof Tenant ? $tenant->name : 'negocio';

                        return 'Confirmar pago de L. '.number_format((float) $record->amount, 2)
                            .' del negocio '.$tenantName.'?';
                    })
                    ->action(function (Payment $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->update([
                                'status' => 'approved',
                                'reviewed_at' => now(),
                            ]);

                            $tenant = $record->tenant;
                            if ($tenant instanceof Tenant) {
                                $tenant->forceFill([
                                    'plan_type' => 'pro',
                                    'subscription_status' => 'active',
                                    'subscription_ends_at' => now()->addMonth(),
                                ])->save();
                            }
                        });

                        $tenant = $record->tenant;
                        $tenantName = $tenant instanceof Tenant ? $tenant->name : 'negocio';

                        Notification::make()
                            ->success()
                            ->title('Pago Aprobado')
                            ->body('El negocio '.$tenantName.' ahora es PRO.')
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
                    ->action(function (Payment $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_at' => now(),
                            'admin_notes' => (string) $data['notes'],
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
                    ->url(fn (Payment $record): string => asset('storage/'.$record->proof_path))
                    ->openUrlInNewTab(),
            ])
            ->poll('30s');
    }

    /**
     * @param  list<string>  $roles
     */
    private static function hasAnyLegacyRole(User $user, array $roles): bool
    {
        $roleValue = (string) $user->getRawOriginal('role');

        return in_array($roleValue, $roles, true);
    }
}
