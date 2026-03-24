<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\Resources\OperationalPlaybookTaskResource\Pages;
use App\Models\OperationalPlaybookTask;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OperationalPlaybookTaskResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = OperationalPlaybookTask::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    public static function getNavigationLabel(): string
    {
        return 'Mesa operativa';
    }

    public static function getModelLabel(): string
    {
        return 'Playbook operativo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Mesa operativa';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gerencia';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = OperationalPlaybookTask::query()
            ->withoutGlobalScopes()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('follow_up_status')
                    ->orWhere('follow_up_status', '!=', OperationalPlaybookTask::FOLLOW_UP_CLOSED);
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes()
                ->with(['tenant.user:id,name,email', 'tenant.crmOwner:id,name,email', 'assignee:id,name,email', 'adminOwner:id,name,email'])
                ->where(function (Builder $innerQuery): void {
                    $innerQuery
                        ->whereNull('follow_up_status')
                        ->orWhere('follow_up_status', '!=', OperationalPlaybookTask::FOLLOW_UP_CLOSED);
                })
                ->orderByRaw("
                    case priority
                        when 'critical' then 1
                        when 'high' then 2
                        else 3
                    end
                ")
                ->orderByRaw("
                    case
                        when due_at is null then 2
                        when due_at < now() then 0
                        else 1
                    end
                ")
                ->orderBy('due_at'))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Playbook')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (OperationalPlaybookTask $record): string => (string) ($record->tenant?->name ?? 'Negocio sin nombre')),
                Tables\Columns\TextColumn::make('queue_label')
                    ->label('Cola')
                    ->badge()
                    ->color(fn (OperationalPlaybookTask $record): string => static::queueColor((string) $record->queue)),
                Tables\Columns\TextColumn::make('follow_up_status_label')
                    ->label('Seguimiento')
                    ->badge()
                    ->color(fn (OperationalPlaybookTask $record): string => static::followUpColor((string) ($record->follow_up_status ?? OperationalPlaybookTask::FOLLOW_UP_NEW))),
                Tables\Columns\TextColumn::make('adminOwner.name')
                    ->label('Owner interno')
                    ->state(fn (OperationalPlaybookTask $record): string => static::resolvedInternalOwnerLabel($record))
                    ->description(fn (OperationalPlaybookTask $record): string => collect([
                        'Equipo: '.($record->tenant?->crm_team_label ?? 'Sin equipo'),
                        'Estado cartera: '.($record->tenant?->crm_status_label ?? 'Nuevo'),
                    ])->implode(' | ')),
                Tables\Columns\TextColumn::make('tenant.crm_priority_label')
                    ->label('Prioridad cartera')
                    ->badge()
                    ->color(fn (OperationalPlaybookTask $record): string => static::tenantPriorityColor($record))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('SLA')
                    ->state(fn (OperationalPlaybookTask $record): string => static::formatDateTime($record->due_at))
                    ->badge()
                    ->color(fn (OperationalPlaybookTask $record): string => $record->isOverdue() ? 'danger' : 'info')
                    ->description(fn (OperationalPlaybookTask $record): string => $record->isOverdue() ? 'SLA vencido' : static::relativeDateTime($record->due_at)),
                Tables\Columns\TextColumn::make('next_follow_up_at')
                    ->label('Siguiente toque')
                    ->state(fn (OperationalPlaybookTask $record): string => static::formatDateTime($record->next_follow_up_at))
                    ->description(fn (OperationalPlaybookTask $record): string => static::relativeDateTime($record->next_follow_up_at))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('internal_notes')
                    ->label('Notas internas')
                    ->limit(80)
                    ->tooltip(fn (OperationalPlaybookTask $record): string => (string) ($record->internal_notes ?? ''))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tenant.crm_notes')
                    ->label('Notas de cuenta')
                    ->limit(80)
                    ->tooltip(fn (OperationalPlaybookTask $record): string => (string) ($record->tenant?->crm_notes ?? ''))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('queue')
                    ->label('Cola')
                    ->options([
                        'activation' => 'Activacion',
                        'leads' => 'Captacion',
                        'appointments' => 'Agenda',
                        'growth' => 'Expansion',
                        'retention' => 'Retencion',
                        'billing' => 'Renovacion',
                    ]),
                Tables\Filters\SelectFilter::make('follow_up_status')
                    ->label('Seguimiento')
                    ->options(static::followUpOptions()),
                Tables\Filters\SelectFilter::make('admin_owner_id')
                    ->label('Owner del caso')
                    ->options(static::internalOwnerOptions())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('tenant_crm_team')
                    ->label('Equipo CRM')
                    ->options(static::crmTeamOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        return $query->whereHas('tenant', fn (Builder $tenantQuery): Builder => $tenantQuery->where('crm_team', $value));
                    }),
                Tables\Filters\SelectFilter::make('tenant_crm_owner_id')
                    ->label('Owner de cartera')
                    ->options(static::internalOwnerOptions())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        return $query->whereHas('tenant', fn (Builder $tenantQuery): Builder => $tenantQuery->where('crm_owner_id', $value));
                    }),
                Tables\Filters\SelectFilter::make('tenant_crm_status')
                    ->label('Estado de cartera')
                    ->options(static::crmStatusOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        return $query->whereHas('tenant', fn (Builder $tenantQuery): Builder => $tenantQuery->where('crm_status', $value));
                    }),
            ])
            ->actions([
                Action::make('openTenant')
                    ->label('Abrir negocio')
                    ->icon('heroicon-o-building-storefront')
                    ->url(fn (OperationalPlaybookTask $record): string => static::tenantHref($record)),
                Action::make('takeOwnership')
                    ->label('Tomar caso')
                    ->icon('heroicon-o-hand-raised')
                    ->color('info')
                    ->visible(fn (OperationalPlaybookTask $record): bool => ! static::isOwnedByCurrentUser($record))
                    ->action(function (OperationalPlaybookTask $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            return;
                        }

                        $nextFollowUpStatus = in_array((string) ($record->follow_up_status ?? ''), [
                            '',
                            OperationalPlaybookTask::FOLLOW_UP_NEW,
                        ], true)
                            ? OperationalPlaybookTask::FOLLOW_UP_ASSIGNED
                            : (string) $record->follow_up_status;

                        $record->forceFill([
                            'admin_owner_id' => (string) $actor->getKey(),
                            'follow_up_status' => $nextFollowUpStatus,
                            'last_followed_up_at' => now(),
                        ])->save();

                        if ($record->tenant instanceof Tenant && blank($record->tenant->crm_owner_id)) {
                            $record->tenant->forceFill([
                                'crm_owner_id' => (string) $actor->getKey(),
                                'crm_last_contacted_at' => now(),
                            ])->save();
                        }

                        static::logInternalFollowUp($record, 'Caso tomado por soporte comercial', [
                            'admin_owner_id' => (string) $actor->getKey(),
                            'follow_up_status' => (string) $record->follow_up_status,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Caso asignado')
                            ->body('Ahora aparece bajo tu ownership interno.')
                            ->send();
                    }),
                Action::make('followUp')
                    ->label('Seguimiento')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->fillForm(fn (OperationalPlaybookTask $record): array => [
                        'admin_owner_id' => $record->admin_owner_id ?: $record->tenant?->crm_owner_id,
                        'follow_up_status' => $record->follow_up_status ?: OperationalPlaybookTask::FOLLOW_UP_NEW,
                        'next_follow_up_at' => optional($record->next_follow_up_at)?->format('Y-m-d\TH:i'),
                        'due_at' => optional($record->due_at)?->format('Y-m-d\TH:i'),
                        'internal_note' => '',
                    ])
                    ->form([
                        Forms\Components\Select::make('admin_owner_id')
                            ->label('Owner interno')
                            ->options(static::internalOwnerOptions())
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin owner interno'),
                        Forms\Components\Select::make('follow_up_status')
                            ->label('Estado de seguimiento')
                            ->options(static::followUpOptions())
                            ->required(),
                        Forms\Components\DateTimePicker::make('next_follow_up_at')
                            ->label('Siguiente toque')
                            ->seconds(false),
                        Forms\Components\DateTimePicker::make('due_at')
                            ->label('SLA interno')
                            ->seconds(false),
                        Forms\Components\Textarea::make('internal_note')
                            ->label('Nota interna')
                            ->rows(5)
                            ->placeholder('Resume el contexto, bloqueo o siguiente paso para soporte comercial.'),
                    ])
                    ->action(function (OperationalPlaybookTask $record, array $data): void {
                        $actor = auth()->user();
                        $selectedOwner = filled($data['admin_owner_id'] ?? null)
                            ? User::query()->find((string) $data['admin_owner_id'])
                            : null;

                        if (! $selectedOwner instanceof User && $actor instanceof User && ($data['follow_up_status'] ?? '') !== OperationalPlaybookTask::FOLLOW_UP_NEW) {
                            $selectedOwner = $actor;
                        }

                        $record->forceFill([
                            'admin_owner_id' => $selectedOwner instanceof User ? (string) $selectedOwner->getKey() : null,
                            'follow_up_status' => (string) ($data['follow_up_status'] ?? OperationalPlaybookTask::FOLLOW_UP_NEW),
                            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                            'due_at' => $data['due_at'] ?? $record->due_at,
                            'internal_notes' => static::appendInternalNote(
                                (string) ($record->internal_notes ?? ''),
                                (string) ($data['internal_note'] ?? ''),
                                $actor instanceof User ? $actor : null,
                            ),
                            'last_followed_up_at' => now(),
                        ])->save();

                        if ($record->tenant instanceof Tenant && $selectedOwner instanceof User) {
                            $record->tenant->forceFill([
                                'crm_owner_id' => (string) $selectedOwner->getKey(),
                                'crm_last_contacted_at' => now(),
                            ])->save();
                        }

                        static::logInternalFollowUp($record, 'Seguimiento interno actualizado', [
                            'admin_owner_id' => $selectedOwner instanceof User ? (string) $selectedOwner->getKey() : null,
                            'follow_up_status' => (string) ($record->follow_up_status ?? OperationalPlaybookTask::FOLLOW_UP_NEW),
                            'next_follow_up_at' => optional($record->next_follow_up_at)?->toIso8601String(),
                        ], $actor instanceof User ? $actor : null);

                        Notification::make()
                            ->success()
                            ->title('Seguimiento actualizado')
                            ->body('El caso ya tiene owner, estado y siguiente toque interno.')
                            ->send();
                    }),
                Action::make('closeFollowUp')
                    ->label('Cerrar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OperationalPlaybookTask $record): bool => (string) ($record->follow_up_status ?? OperationalPlaybookTask::FOLLOW_UP_NEW) !== OperationalPlaybookTask::FOLLOW_UP_CLOSED)
                    ->action(function (OperationalPlaybookTask $record): void {
                        $actor = auth()->user();

                        $record->forceFill([
                            'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_CLOSED,
                            'next_follow_up_at' => null,
                            'last_followed_up_at' => now(),
                        ])->save();

                        static::logInternalFollowUp($record, 'Caso cerrado desde superadmin', [
                            'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_CLOSED,
                        ], $actor instanceof User ? $actor : null);

                        Notification::make()
                            ->success()
                            ->title('Caso cerrado')
                            ->body('Ya salio de la mesa operativa activa.')
                            ->send();
                    }),
            ])
            ->defaultSort('due_at')
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'Superadmin',
                'heading' => 'Todavia no hay casos activos',
                'description' => 'Cuando los tenants disparen leads, citas, churn o renovaciones, apareceran aqui para el equipo interno.',
                'buttonLabel' => 'Volver al tablero',
                'buttonUrl' => route('filament.admin.pages.dashboard', [], false),
            ]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperationalPlaybookTasks::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function internalOwnerOptions(): array
    {
        $superAdmins = collect(config('auth.super_admins', []))
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->values()
            ->all();

        return User::query()
            ->where(function (Builder $query) use ($superAdmins): void {
                $query->where('role', UserRole::SUPER_ADMIN->value);

                if ($superAdmins !== []) {
                    $query->orWhereIn('email', $superAdmins);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function followUpOptions(): array
    {
        return [
            OperationalPlaybookTask::FOLLOW_UP_NEW => 'Nuevo',
            OperationalPlaybookTask::FOLLOW_UP_ASSIGNED => 'Asignado',
            OperationalPlaybookTask::FOLLOW_UP_WAITING_CUSTOMER => 'Esperando cliente',
            OperationalPlaybookTask::FOLLOW_UP_SCHEDULED => 'Siguiente toque',
            OperationalPlaybookTask::FOLLOW_UP_ESCALATED => 'Escalado',
            OperationalPlaybookTask::FOLLOW_UP_CLOSED => 'Cerrado',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function crmTeamOptions(): array
    {
        return [
            Tenant::CRM_TEAM_ACTIVATION => 'Activacion',
            Tenant::CRM_TEAM_GROWTH => 'Growth',
            Tenant::CRM_TEAM_RETENTION => 'Retencion',
            Tenant::CRM_TEAM_BILLING => 'Cobros',
            Tenant::CRM_TEAM_SUPPORT => 'Soporte',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function crmStatusOptions(): array
    {
        return [
            Tenant::CRM_STATUS_NEW => 'Nuevo',
            Tenant::CRM_STATUS_ONBOARDING => 'En onboarding',
            Tenant::CRM_STATUS_ACTIVE => 'Operando',
            Tenant::CRM_STATUS_UPSELL => 'Upsell',
            Tenant::CRM_STATUS_RENEWAL => 'Renovacion',
            Tenant::CRM_STATUS_AT_RISK => 'En riesgo',
            Tenant::CRM_STATUS_PAUSED => 'Pausado',
        ];
    }

    protected static function queueColor(string $queue): string
    {
        return match ($queue) {
            'activation' => 'warning',
            'appointments' => 'info',
            'growth' => 'primary',
            'retention' => 'success',
            'billing' => 'danger',
            default => 'warning',
        };
    }

    protected static function followUpColor(string $status): string
    {
        return match ($status) {
            OperationalPlaybookTask::FOLLOW_UP_ASSIGNED => 'info',
            OperationalPlaybookTask::FOLLOW_UP_WAITING_CUSTOMER => 'warning',
            OperationalPlaybookTask::FOLLOW_UP_SCHEDULED => 'success',
            OperationalPlaybookTask::FOLLOW_UP_ESCALATED => 'danger',
            OperationalPlaybookTask::FOLLOW_UP_CLOSED => 'gray',
            default => 'warning',
        };
    }

    protected static function tenantHref(OperationalPlaybookTask $record): string
    {
        $tenant = $record->tenant;

        return $tenant instanceof Tenant
            ? TenantResource::getUrl('edit', ['record' => $tenant])
            : route('filament.admin.resources.tenants.index', [], false);
    }

    protected static function isOwnedByCurrentUser(OperationalPlaybookTask $record): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $resolvedOwnerId = $record->admin_owner_id ?: $record->tenant?->crm_owner_id;

        return (string) $resolvedOwnerId === (string) $user->getKey();
    }

    protected static function formatDateTime(mixed $value): string
    {
        if (! $value instanceof \DateTimeInterface) {
            return 'Sin fecha';
        }

        return $value->format('d/m H:i');
    }

    protected static function relativeDateTime(mixed $value): string
    {
        if (! $value instanceof \Illuminate\Support\Carbon) {
            return 'Sin seguimiento agendado';
        }

        return $value->diffForHumans();
    }

    protected static function presetLabel(string $presetKey): string
    {
        if ($presetKey === '') {
            return 'Sin sugerencia';
        }

        return 'Sugerido: '.Str::of($presetKey)->replace('_', ' ')->title()->value();
    }

    protected static function resolvedInternalOwnerLabel(OperationalPlaybookTask $record): string
    {
        if ($record->adminOwner instanceof User) {
            return (string) ($record->adminOwner->name ?: $record->adminOwner->email);
        }

        if ($record->tenant?->crmOwner instanceof User) {
            return (string) ($record->tenant->crmOwner->name ?: $record->tenant->crmOwner->email).' (cartera)';
        }

        return 'Sin owner interno';
    }

    protected static function tenantPriorityColor(OperationalPlaybookTask $record): string
    {
        return match ((string) ($record->tenant?->getAttributes()['crm_priority'] ?? Tenant::CRM_PRIORITY_NORMAL)) {
            Tenant::CRM_PRIORITY_CRITICAL => 'danger',
            Tenant::CRM_PRIORITY_HIGH => 'warning',
            Tenant::CRM_PRIORITY_LOW => 'gray',
            default => 'info',
        };
    }

    protected static function appendInternalNote(string $existing, string $incoming, ?User $actor = null): string
    {
        $incoming = trim($incoming);

        if ($incoming === '') {
            return $existing;
        }

        $prefix = now()->format('d/m/Y H:i').' - '.($actor?->name ?? 'Sistema');
        $entry = $prefix.': '.$incoming;
        $existing = trim($existing);

        return $existing === '' ? $entry : $entry."\n\n".$existing;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function logInternalFollowUp(OperationalPlaybookTask $task, string $description, array $properties = [], ?User $actor = null): void
    {
        $tenant = $task->tenant;

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenant->logOperationalEvent($description, $actor, array_merge($properties, [
            'playbook_task_id' => (string) $task->getKey(),
            'queue' => (string) $task->queue,
            'admin_owner_id' => (string) ($task->admin_owner_id ?? ''),
            'follow_up_status' => (string) ($task->follow_up_status ?? OperationalPlaybookTask::FOLLOW_UP_NEW),
        ]));
    }
}
