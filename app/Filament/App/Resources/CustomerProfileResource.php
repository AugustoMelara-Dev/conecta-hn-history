<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Domain\TenantGovernance\Services\CustomerLifecycleTriggerManager;
use App\Filament\App\Resources\CustomerProfileResource\Pages;
use App\Filament\App\Resources\CustomerProfileResource\RelationManagers;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerProfileResource extends Resource
{
    protected static ?string $model = CustomerProfile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function canViewAny(): bool
    {
        return static::getTenantRecord() instanceof Tenant;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Clientes';
    }

    public static function getModelLabel(): string
    {
        return 'Cliente';
    }

    public static function getPluralModelLabel(): string
    {
        return 'CRM de clientes';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $count = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('reactivation_score', '>=', 65)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Clientes con senal fuerte de reactivacion';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Ficha del cliente')
                    ->description('Resumen unificado para ventas, servicio y reactivacion.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefono')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                        Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('orders_count')
                                    ->label('Pedidos')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('repeat_purchase_count')
                                    ->label('Recompras')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('appointments_count')
                                    ->label('Citas')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('leads_count')
                                    ->label('Prospectos')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('total_spent')
                                    ->label('Valor acumulado')
                                    ->prefix('L. ')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Retencion y fidelidad')
                    ->description('Lecturas utiles para recompra, segmentacion y continuidad.')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('acquisition_channel')
                                    ->label('Canal de entrada')
                                    ->formatStateUsing(fn (CustomerProfile $record): string => $record->acquisition_channel_label)
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('loyalty_tier')
                                    ->label('Tier')
                                    ->formatStateUsing(fn (CustomerProfile $record): string => $record->loyalty_tier_label)
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('loyalty_points_balance')
                                    ->label('Puntos estimados')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('initial_ltv')
                                    ->label('LTV inicial')
                                    ->prefix('L. ')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Operacion comercial')
                    ->description('Campos editables para seguimiento, segmentacion y activacion.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('preferred_channel')
                                    ->label('Canal preferido')
                                    ->options([
                                        'whatsapp' => 'WhatsApp',
                                        'email' => 'Email',
                                        'phone' => 'Llamada',
                                        'in_person' => 'Presencial',
                                    ])
                                    ->native(false)
                                    ->required(),
                                Forms\Components\Select::make('manual_segment')
                                    ->label('Segmento manual')
                                    ->placeholder('Usar segmento calculado')
                                    ->options([
                                        'vip' => 'VIP',
                                        'repeat' => 'Recurrente',
                                        'reactivation' => 'Reactivacion',
                                        'booked_service' => 'Con citas',
                                        'active' => 'Activo',
                                        'prospect' => 'Prospecto',
                                        'new' => 'Nuevo',
                                    ])
                                    ->native(false),
                                Forms\Components\DateTimePicker::make('last_contacted_at')
                                    ->label('Ultimo contacto')
                                    ->seconds(false),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('marketing_opt_in')
                                    ->label('Opt-in marketing'),
                                Forms\Components\Toggle::make('whatsapp_opt_in')
                                    ->label('Opt-in WhatsApp'),
                                Forms\Components\Toggle::make('email_opt_in')
                                    ->label('Opt-in email'),
                            ]),
                        TagsInput::make('tags')
                            ->label('Etiquetas')
                            ->separator(',')
                            ->placeholder('vip, control, frecuente'),
                        Forms\Components\Textarea::make('crm_notes')
                            ->label('Notas internas')
                            ->rows(5)
                            ->placeholder('Ejemplo: suele responder por WhatsApp al final del dia, conviene ofrecer recompra en 3 semanas.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->latest('last_seen_at'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable(['name', 'email', 'phone'])
                    ->weight('bold')
                    ->description(fn (CustomerProfile $record): string => collect([
                        $record->email,
                        $record->phone,
                    ])->filter()->implode(' | ')),
                Tables\Columns\TextColumn::make('segment_display')
                    ->label('Segmento')
                    ->badge()
                    ->state(fn (CustomerProfile $record): string => $record->segment_display_label)
                    ->color(fn (CustomerProfile $record): string => match ($record->segment_display) {
                        'vip' => 'success',
                        'repeat' => 'info',
                        'reactivation' => 'warning',
                        'prospect' => 'gray',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('lifecycle_stage')
                    ->label('Ciclo')
                    ->badge()
                    ->state(fn (CustomerProfile $record): string => $record->lifecycle_stage_label)
                    ->color(fn (CustomerProfile $record): string => match ($record->lifecycle_stage) {
                        'loyal' => 'success',
                        'active' => 'info',
                        'dormant' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Valor')
                    ->money('HNL')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('loyalty_tier')
                    ->label('Fidelidad')
                    ->badge()
                    ->state(fn (CustomerProfile $record): string => $record->loyalty_tier_label)
                    ->color(fn (CustomerProfile $record): string => match ($record->loyalty_tier) {
                        'vip' => 'success',
                        'gold' => 'warning',
                        'silver' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->badge(),
                Tables\Columns\TextColumn::make('repeat_purchase_count')
                    ->label('Recompra')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('appointments_count')
                    ->label('Citas')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('leads_count')
                    ->label('Prospectos')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reactivation_score')
                    ->label('Reactivacion')
                    ->badge()
                    ->state(fn (CustomerProfile $record): string => $record->reactivation_label)
                    ->color(fn (CustomerProfile $record): string => match (true) {
                        $record->reactivation_score >= 85 => 'danger',
                        $record->reactivation_score >= 65 => 'warning',
                        $record->reactivation_score >= 45 => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Ultima actividad')
                    ->since()
                    ->sortable()
                    ->description(fn (CustomerProfile $record): string => $record->preferred_channel_label),
                Tables\Columns\TextColumn::make('last_contacted_at')
                    ->label('Ultimo contacto')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lifecycle_stage')
                    ->label('Ciclo')
                    ->options([
                        'prospect' => 'Prospecto',
                        'active' => 'Activo',
                        'loyal' => 'Leal',
                        'dormant' => 'Dormido',
                    ]),
                Tables\Filters\SelectFilter::make('customer_segment')
                    ->label('Segmento')
                    ->options([
                        'vip' => 'VIP',
                        'repeat' => 'Recurrente',
                        'reactivation' => 'Reactivacion',
                        'booked_service' => 'Con citas',
                        'active' => 'Activo',
                        'prospect' => 'Prospecto',
                        'new' => 'Nuevo',
                    ]),
                Tables\Filters\SelectFilter::make('preferred_channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        'phone' => 'Llamada',
                        'in_person' => 'Presencial',
                    ]),
                Tables\Filters\SelectFilter::make('loyalty_tier')
                    ->label('Fidelidad')
                    ->options([
                        'vip' => 'VIP',
                        'gold' => 'Gold',
                        'silver' => 'Silver',
                        'base' => 'Base',
                    ]),
                Tables\Filters\Filter::make('reactivation')
                    ->label('Solo reactivacion')
                    ->query(fn ($query) => $query->where('reactivation_score', '>=', 65)),
            ])
            ->actions([
                Action::make('email_follow_up')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->modalHeading('Enviar seguimiento por email')
                    ->modalDescription('Esto crea una entrega auditada en el CRM y actualiza el ultimo contacto del cliente.')
                    ->fillForm(fn (CustomerProfile $record): array => [
                        'automation_key' => 'manual_follow_up',
                        ...app(CustomerAutomationManager::class)->preview($record, 'manual_follow_up'),
                    ])
                    ->form([
                        Forms\Components\Hidden::make('automation_key'),
                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('body')
                            ->label('Mensaje')
                            ->rows(8)
                            ->required(),
                    ])
                    ->visible(fn (CustomerProfile $record): bool => filled($record->email))
                    ->action(function (CustomerProfile $record, array $data): void {
                        $touchpoint = app(CustomerAutomationManager::class)->sendEmailTouchpoint(
                            profile: $record,
                            subject: (string) $data['subject'],
                            body: (string) $data['body'],
                            automationKey: (string) ($data['automation_key'] ?? 'manual_follow_up'),
                            actorUserId: auth()->id(),
                        );

                        Notification::make()
                            ->title(match ($touchpoint->status) {
                                CustomerTouchpoint::STATUS_SENT => 'Seguimiento enviado',
                                CustomerTouchpoint::STATUS_SKIPPED => 'Contacto omitido',
                                CustomerTouchpoint::STATUS_FAILED => 'No se pudo enviar',
                                default => 'Contacto registrado',
                            })
                            ->color(match ($touchpoint->status) {
                                CustomerTouchpoint::STATUS_SENT => 'success',
                                CustomerTouchpoint::STATUS_SKIPPED => 'warning',
                                CustomerTouchpoint::STATUS_FAILED => 'danger',
                                default => 'info',
                            })
                            ->body((string) ($touchpoint->last_error ?: 'El CRM ya guardo este movimiento en el historial del cliente.'))
                            ->send();
                    }),
                Action::make('whatsapp_follow_up')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar seguimiento por WhatsApp')
                    ->modalDescription('Esto usa una plantilla auditable de WhatsApp Cloud API y deja trazabilidad en el CRM.')
                    ->visible(fn (CustomerProfile $record): bool => filled($record->phone))
                    ->action(function (CustomerProfile $record): void {
                        $touchpoint = app(CustomerAutomationManager::class)->sendSuggestedAutomation(
                            profile: $record,
                            automationKey: 'manual_follow_up_whatsapp',
                            actorUserId: auth()->id(),
                            channel: 'whatsapp',
                        );

                        Notification::make()
                            ->title(match ($touchpoint->status) {
                                CustomerTouchpoint::STATUS_SENT => 'Seguimiento enviado',
                                CustomerTouchpoint::STATUS_SKIPPED => 'Contacto omitido',
                                CustomerTouchpoint::STATUS_FAILED => 'No se pudo enviar',
                                default => 'Contacto registrado',
                            })
                            ->color(match ($touchpoint->status) {
                                CustomerTouchpoint::STATUS_SENT => 'success',
                                CustomerTouchpoint::STATUS_SKIPPED => 'warning',
                                CustomerTouchpoint::STATUS_FAILED => 'danger',
                                default => 'info',
                            })
                            ->body((string) ($touchpoint->last_error ?: 'El seguimiento por WhatsApp ya quedo auditado en el CRM.'))
                            ->send();
                    }),
                Action::make('reactivation_email')
                    ->label('Reactivar')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Lanzar reactivacion')
                    ->modalDescription('Usa el mejor canal segun el cliente y respeta opt-in antes de enviar.')
                    ->visible(fn (CustomerProfile $record): bool => (int) $record->reactivation_score >= 65 && filled($record->email))
                    ->action(function (CustomerProfile $record): void {
                        $touchpoint = app(CustomerLifecycleTriggerManager::class)->triggerJourney(
                            profile: $record,
                            journeyKey: 'reactivation',
                            actorUserId: auth()->id(),
                        );

                        Notification::make()
                            ->title(match ($touchpoint?->status) {
                                CustomerTouchpoint::STATUS_SENT => 'Reactivacion enviada',
                                CustomerTouchpoint::STATUS_SKIPPED => 'Reactivacion omitida',
                                CustomerTouchpoint::STATUS_FAILED => 'No se pudo enviar',
                                default => 'Reactivacion procesada',
                            })
                            ->color(match ($touchpoint?->status) {
                                CustomerTouchpoint::STATUS_SENT => 'success',
                                CustomerTouchpoint::STATUS_SKIPPED => 'warning',
                                CustomerTouchpoint::STATUS_FAILED => 'danger',
                                default => 'info',
                            })
                            ->body((string) ($touchpoint?->last_error ?: 'El CRM ya registro la accion de reactivacion para este cliente.'))
                            ->send();
                    }),
                EditAction::make()
                    ->label('Abrir ficha')
                    ->icon('heroicon-o-arrow-right'),
            ])
            ->bulkActions([])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'CRM',
                'heading' => 'Todavia no hay clientes consolidados',
                'description' => 'Cuando entren pedidos, citas o prospectos con contacto, el CRM armara la ficha automaticamente.',
            ]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\LeadsRelationManager::class,
            RelationManagers\TouchpointsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerProfiles::route('/'),
            'edit' => Pages\EditCustomerProfile::route('/{record}/edit'),
        ];
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
