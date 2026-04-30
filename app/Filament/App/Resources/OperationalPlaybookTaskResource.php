<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Resources\Leads\LeadResource;
use App\Filament\App\Resources\OperationalPlaybookTaskResource\Pages;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OperationalPlaybookTaskResource extends Resource
{
    protected static ?string $model = OperationalPlaybookTask::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();
        $user = auth()->user();

        return $tenant instanceof Tenant
            && $user instanceof User
            && app(TenantPermissionGuardInterface::class)->allows(
                $user,
                $tenant,
                Capability::NOTIFICATIONS_BASIC,
                Permission::PLAYBOOKS_VIEW,
            );
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

    public static function getNavigationLabel(): string
    {
        return 'Centro operativo';
    }

    public static function getModelLabel(): string
    {
        return 'Playbook';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Centro operativo';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operación';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $count = OperationalPlaybookTask::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereIn('status', [
                OperationalPlaybookTask::STATUS_OPEN,
                OperationalPlaybookTask::STATUS_IN_PROGRESS,
            ])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $tenant = static::getTenantRecord();

                if (! $tenant instanceof Tenant) {
                    return $query->whereRaw('1 = 0');
                }

                return static::applyPriorityOrdering(
                    $query
                        ->where('tenant_id', (string) $tenant->getKey())
                        ->with('assignee')
                );
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Playbook')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (OperationalPlaybookTask $record): string => (string) ($record->summary ?? '')),
                Tables\Columns\TextColumn::make('queue_label')
                    ->label('Cola')
                    ->badge()
                    ->color(fn (OperationalPlaybookTask $record): string => match ((string) $record->queue) {
                        'appointments' => 'info',
                        'retention' => 'success',
                        'billing' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('priority_label')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (OperationalPlaybookTask $record): string => match ((string) $record->priority) {
                        OperationalPlaybookTask::PRIORITY_CRITICAL => 'danger',
                        OperationalPlaybookTask::PRIORITY_HIGH => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (OperationalPlaybookTask $record): string => match ((string) $record->status) {
                        OperationalPlaybookTask::STATUS_COMPLETED => 'success',
                        OperationalPlaybookTask::STATUS_IN_PROGRESS => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Responsable')
                    ->default('Sin asignar')
                    ->description(fn (OperationalPlaybookTask $record): string => (string) ($record->suggested_preset_key ?? '')),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('SLA')
                    ->state(function (OperationalPlaybookTask $record): string {
                        $dueAt = static::recordCarbon($record->due_at);

                        if ($dueAt === null) {
                            return 'Sin SLA';
                        }

                        return $dueAt->format('d/m H:i');
                    })
                    ->badge()
                    ->color(function (OperationalPlaybookTask $record): string {
                        if (! $record->due_at) {
                            return 'gray';
                        }

                        if ($record->completed_at !== null) {
                            return 'success';
                        }

                        return $record->isOverdue() ? 'danger' : 'info';
                    })
                    ->description(function (OperationalPlaybookTask $record): string {
                        $dueAt = static::recordCarbon($record->due_at);

                        if ($dueAt === null) {
                            return 'Sin vencimiento';
                        }

                        if ($record->completed_at !== null) {
                            return 'Resuelto';
                        }

                        return $record->isOverdue()
                            ? 'SLA vencido'
                            : $dueAt->diffForHumans();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        OperationalPlaybookTask::STATUS_OPEN => 'Abierta',
                        OperationalPlaybookTask::STATUS_IN_PROGRESS => 'En curso',
                        OperationalPlaybookTask::STATUS_COMPLETED => 'Completada',
                    ]),
                Tables\Filters\SelectFilter::make('queue')
                    ->label('Cola')
                    ->options([
                        'leads' => 'Captacion',
                        'appointments' => 'Agenda',
                        'retention' => 'Retencion',
                        'billing' => 'Renovación',
                    ]),
                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('Responsable')
                    ->options(static::assigneeOptions()),
            ])
            ->actions([
                Action::make('openModule')
                    ->label('Abrir modulo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (OperationalPlaybookTask $record): string => static::resolveTaskHref($record)),
                Action::make('claim')
                    ->label('Tomar')
                    ->icon('heroicon-o-hand-raised')
                    ->color('info')
                    ->visible(fn (OperationalPlaybookTask $record): bool => blank($record->assigned_user_id) && static::canManageTasks())
                    ->action(function (OperationalPlaybookTask $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        $record->reassignTo($user);
                        $record->markInProgress($user);
                        static::logTaskEvent($record, 'Playbook tomado por el equipo', [
                            'task_title' => $record->title,
                            'assigned_user_id' => (string) $user->getKey(),
                        ], $user);

                        Notification::make()
                            ->success()
                            ->title('Playbook asignado')
                            ->body('Ahora aparece dentro de tu cola operativa.')
                            ->send();
                    }),
                Action::make('start')
                    ->label('Iniciar')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (OperationalPlaybookTask $record): bool => $record->status === OperationalPlaybookTask::STATUS_OPEN && static::canManageTasks())
                    ->action(function (OperationalPlaybookTask $record): void {
                        $user = auth()->user();
                        $record->markInProgress($user instanceof User ? $user : null);
                        static::logTaskEvent($record, 'Playbook en ejecucion', [
                            'task_title' => $record->title,
                        ], $user instanceof User ? $user : null);

                        Notification::make()
                            ->success()
                            ->title('Playbook en curso')
                            ->body('Ya quedo marcado como frente activo.')
                            ->send();
                    }),
                Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (OperationalPlaybookTask $record): bool => $record->status !== OperationalPlaybookTask::STATUS_COMPLETED && static::canManageTasks())
                    ->action(function (OperationalPlaybookTask $record): void {
                        $user = auth()->user();
                        $record->markCompleted($user instanceof User ? $user : null);
                        static::logTaskEvent($record, 'Playbook completado', [
                            'task_title' => $record->title,
                            'playbook_task_id' => (string) $record->getKey(),
                        ], $user instanceof User ? $user : null);

                        Notification::make()
                            ->success()
                            ->title('Playbook completado')
                            ->body('Buen trabajo. Esta tarea ya salio de la cola activa.')
                            ->send();
                    }),
                Action::make('reassign')
                    ->label('Reasignar')
                    ->icon('heroicon-o-user-plus')
                    ->color('gray')
                    ->visible(fn (): bool => static::canAssignTasks())
                    ->form([
                        Select::make('assigned_user_id')
                            ->label('Responsable')
                            ->options(static::assigneeOptions())
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin asignar'),
                    ])
                    ->fillForm(fn (OperationalPlaybookTask $record): array => [
                        'assigned_user_id' => $record->assigned_user_id,
                    ])
                    ->action(function (OperationalPlaybookTask $record, array $data): void {
                        $user = filled($data['assigned_user_id'] ?? null)
                            ? User::query()->find((string) $data['assigned_user_id'])
                            : null;

                        $record->reassignTo($user instanceof User ? $user : null);
                        static::logTaskEvent($record, 'Playbook reasignado', [
                            'task_title' => $record->title,
                            'assigned_user_id' => $user instanceof User ? (string) $user->getKey() : null,
                        ], auth()->user() instanceof User ? auth()->user() : null);

                        Notification::make()
                            ->success()
                            ->title('Responsable actualizado')
                            ->body('La cola del equipo ya refleja el nuevo dueno.')
                            ->send();
                    }),
            ])
            ->defaultSort('due_at')
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'Operación',
                'heading' => 'Todavía no tienes playbooks activos',
                'description' => 'Los leads, citas, abandonos, recompras y renovaciones irán cayendo aquí con responsable sugerido y SLA.',
                'buttonLabel' => 'Volver al escritorio',
                'buttonUrl' => route('filament.app.pages.dashboard', ['tenant' => static::getTenantRecord()?->slug], false),
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
    protected static function assigneeOptions(): array
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        $options = [];

        if ($tenant->user instanceof User) {
            $options[(string) $tenant->user->getKey()] = $tenant->user->name.' (dueno)';
        }

        TenantMembership::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereNotNull('accepted_at')
            ->with('user')
            ->get()
            ->each(function (TenantMembership $membership) use (&$options): void {
                $user = $membership->user;

                if (! $user instanceof User) {
                    return;
                }

                $label = $user->name;
                $presetKey = (string) ($membership->preset_key ?? '');

                if ($presetKey !== '') {
                    $label .= ' - '.$presetKey;
                }

                $options[(string) $user->getKey()] = $label;
            });

        return $options;
    }

    protected static function resolveTaskHref(OperationalPlaybookTask $record): string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return '#';
        }

        $tenantSlug = $tenant->slug;
        $relatedId = $record->related_id;

        return match ((string) $record->related_type) {
            Lead::class => LeadResource::getUrl('edit', ['tenant' => $tenantSlug, 'record' => $relatedId]),
            Appointment::class => AppointmentResource::getUrl('edit', ['tenant' => $tenantSlug, 'record' => $relatedId]),
            Order::class => OrderResource::getUrl('view', ['tenant' => $tenantSlug, 'record' => $relatedId]),
            Service::class => ServiceResource::getUrl('edit', ['tenant' => $tenantSlug, 'record' => $relatedId]),
            default => static::fallbackHref((string) $record->route_key, $tenantSlug),
        };
    }

    protected static function fallbackHref(string $routeKey, string $tenantSlug): string
    {
        return match ($routeKey) {
            'leads' => LeadResource::getUrl('index', ['tenant' => $tenantSlug]),
            'appointments' => AppointmentResource::getUrl('index', ['tenant' => $tenantSlug]),
            'orders' => OrderResource::getUrl('index', ['tenant' => $tenantSlug]),
            'services' => ServiceResource::getUrl('index', ['tenant' => $tenantSlug]),
            'team' => TenantUserResource::getUrl('index', ['tenant' => $tenantSlug]),
            'marketing' => route('filament.app.pages.manage-marketing', ['tenant' => $tenantSlug], false),
            'billing' => route('filament.app.pages.manage-billing', ['tenant' => $tenantSlug], false),
            'profile' => route('filament.app.pages.settings', ['tenant' => $tenantSlug], false),
            default => route('filament.app.pages.dashboard', ['tenant' => $tenantSlug], false),
        };
    }

    protected static function applyPriorityOrdering(Builder $query): Builder
    {
        return $query
            ->orderByRaw("
                case priority
                    when 'critical' then 1
                    when 'high' then 2
                    else 3
                end
            ")
            ->orderByRaw('
                case
                    when due_at is null then 2
                    when due_at < '.static::currentTimestampSql().' then 0
                    else 1
                end
            ')
            ->orderBy('due_at');
    }

    protected static function currentTimestampSql(): string
    {
        return match ((string) config('database.default', 'mysql')) {
            'sqlite' => 'CURRENT_TIMESTAMP',
            default => 'now()',
        };
    }

    protected static function canManageTasks(): bool
    {
        return static::checkPermission(Permission::PLAYBOOKS_MANAGE);
    }

    protected static function canAssignTasks(): bool
    {
        return static::checkPermission(Permission::PLAYBOOKS_ASSIGN);
    }

    protected static function checkPermission(Permission $permission): bool
    {
        $tenant = static::getTenantRecord();
        $user = auth()->user();

        return $tenant instanceof Tenant
            && $user instanceof User
            && app(TenantPermissionGuardInterface::class)->allows(
                $user,
                $tenant,
                Capability::NOTIFICATIONS_BASIC,
                $permission,
            );
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    protected static function recordCarbon(mixed $value): ?\Illuminate\Support\Carbon
    {
        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return \Illuminate\Support\Carbon::parse($value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function logTaskEvent(OperationalPlaybookTask $task, string $description, array $properties = [], ?User $actor = null): void
    {
        $tenant = $task->tenant;

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenant->logOperationalEvent($description, $actor, array_merge($properties, [
            'playbook_task_id' => (string) $task->getKey(),
            'queue' => (string) $task->queue,
        ]));
    }
}
