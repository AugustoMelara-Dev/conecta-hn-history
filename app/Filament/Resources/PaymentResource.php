<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * PaymentResource - Subscription payment review center.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

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

    public static function getNavigationBadge(): ?string
    {
        $count = SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Información del Pago')
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

                        Forms\Components\TextInput::make('reference_number')
                            ->label('Referencia Bancaria')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                                SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                                SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                            ])
                            ->disabled(),

                        Forms\Components\FileUpload::make('proof_path')
                            ->label('Comprobante de Pago')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(5120)
                            ->image()
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notas Administrativas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Referencia')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->copyMessageDuration(1500)
                    ->placeholder('Sin referencia')
                    ->fontFamily('mono')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(function (SubscriptionPayment $record): string {
                        $tenant = $record->tenant()->first();

                        if (! $tenant instanceof Tenant) {
                            return 'Sin email';
                        }

                        return $tenant->user()->value('email') ?? 'Sin email';
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('HNL')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\ImageColumn::make('proof_path')
                    ->label('Comprobante')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SubscriptionPayment::STATUS_PENDING => 'warning',
                        SubscriptionPayment::STATUS_APPROVED => 'success',
                        SubscriptionPayment::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        SubscriptionPayment::STATUS_PENDING => 'heroicon-m-clock',
                        SubscriptionPayment::STATUS_APPROVED => 'heroicon-m-check-circle',
                        SubscriptionPayment::STATUS_REJECTED => 'heroicon-m-x-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                        SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                        SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reportado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->size('sm')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Procesado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                        SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                        SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                    ]),

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
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-banknotes')
                    ->modalHeading('Confirmar recepcion de fondos?')
                    ->modalDescription('Al aprobar, el negocio sera elevado a Plan PRO por 30 dias.')
                    ->modalSubmitActionLabel('Si, aprobar pago')
                    ->visible(fn (SubscriptionPayment $record) => $record->isPending())
                    ->action(function (SubscriptionPayment $record): void {
                        $adminId = (string) auth()->id();

                        if ($adminId === '') {
                            Notification::make()
                                ->danger()
                                ->title('Sesion invalida')
                                ->body('No se pudo identificar al administrador revisor.')
                                ->send();

                            return;
                        }

                        $record->approve($adminId);
                        $record->refresh();
                        $record->loadMissing('tenant.user');

                        $tenant = $record->tenant()->first();

                        if ($tenant instanceof Tenant) {
                            $user = $tenant->user()->first();
                            $subscriptionEndsAt = $tenant->getAttribute('subscription_ends_at');
                            $endDate = $subscriptionEndsAt instanceof Carbon ? $subscriptionEndsAt->format('d/m/Y') : null;
                            $tenantName = (string) $tenant->getAttribute('name');

                            static::logTenantPaymentEvent(
                                $tenant,
                                'Pago PRO aprobado desde finanzas',
                                [
                                    'payment_id' => (string) $record->getKey(),
                                    'payment_reference' => (string) ($record->reference_number ?? ''),
                                    'payment_amount' => 'L. '.number_format((float) $record->amount, 2),
                                    'status' => SubscriptionPayment::STATUS_APPROVED,
                                    'subscription_ends_at' => $endDate,
                                ],
                            );

                            if ($user instanceof User && $endDate) {
                                static::sendNotificationToUser(
                                    $user,
                                    Notification::make()
                                        ->success()
                                        ->title('Pago Aprobado')
                                        ->body("Tu Plan PRO para {$tenantName} esta activo hasta el {$endDate}.")
                                );
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Pago aprobado')
                            ->body('Cuenta PRO activada y pago marcado correctamente.')
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SubscriptionPayment $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar pago reportado')
                    ->modalDescription('Esta acción rechazará el pago, notificará al negocio y no activará el plan PRO.')
                    ->modalSubmitActionLabel('Sí, rechazar pago')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo del Rechazo')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ej: Comprobante ilegible, monto incorrecto, etc.'),
                    ])
                    ->action(function (SubscriptionPayment $record, array $data): void {
                        $adminId = (string) auth()->id();

                        if ($adminId === '') {
                            Notification::make()
                                ->danger()
                                ->title('Sesion invalida')
                                ->body('No se pudo identificar al administrador revisor.')
                                ->send();

                            return;
                        }

                        $record->reject($adminId, $data['rejection_reason']);

                        $tenant = $record->tenant()->first();
                        $user = $tenant instanceof Tenant ? $tenant->user()->first() : null;

                        if ($tenant instanceof Tenant) {
                            static::logTenantPaymentEvent(
                                $tenant,
                                'Pago PRO rechazado desde finanzas',
                                [
                                    'payment_id' => (string) $record->getKey(),
                                    'payment_reference' => (string) ($record->reference_number ?? ''),
                                    'payment_amount' => 'L. '.number_format((float) $record->amount, 2),
                                    'status' => SubscriptionPayment::STATUS_REJECTED,
                                    'reason' => (string) ($data['rejection_reason'] ?? ''),
                                ],
                            );
                        }

                        if ($user instanceof User) {
                            static::sendNotificationToUser(
                                $user,
                                Notification::make()
                                    ->danger()
                                    ->title('Pago Rechazado')
                                    ->body($data['rejection_reason'])
                            );
                        }

                        Notification::make()
                            ->warning()
                            ->title('Pago rechazado')
                            ->body('Se notifico al cliente.')
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin pagos pendientes')
            ->emptyStateDescription('Los pagos reportados apareceran aqui.')
            ->emptyStateIcon('heroicon-o-banknotes');
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

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<\Illuminate\Database\Eloquent\Model> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->withoutGlobalScopes()
            ->with(['tenant.user'])
            ->orderByRaw("CASE status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                ELSE 4 END");
    }

    protected static function sendNotificationToUser(User $user, Notification $notification): void
    {
        try {
            $notification->sendToDatabase($user);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function logTenantPaymentEvent(Tenant $tenant, string $description, array $properties = []): void
    {
        $actor = auth()->user();

        $tenant->logOperationalEvent(
            $description,
            $actor instanceof User ? $actor : null,
            $properties,
        );
    }
}
