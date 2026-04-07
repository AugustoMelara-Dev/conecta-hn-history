<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\AllowsTrustedTenantAssignment;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class OperationalPlaybookTask extends Model
{
    use AllowsTrustedTenantAssignment, BelongsToTenant, HasFactory, HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_NORMAL = 'normal';

    public const FOLLOW_UP_NEW = 'new';

    public const FOLLOW_UP_ASSIGNED = 'assigned';

    public const FOLLOW_UP_WAITING_CUSTOMER = 'waiting_customer';

    public const FOLLOW_UP_SCHEDULED = 'scheduled';

    public const FOLLOW_UP_ESCALATED = 'escalated';

    public const FOLLOW_UP_CLOSED = 'closed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'assigned_user_id',
        'admin_owner_id',
        'trigger_key',
        'queue',
        'route_key',
        'segment_key',
        'suggested_preset_key',
        'priority',
        'status',
        'follow_up_status',
        'sla_minutes',
        'title',
        'summary',
        'playbook_steps',
        'related_type',
        'related_id',
        'meta',
        'internal_notes',
        'due_at',
        'next_follow_up_at',
        'last_followed_up_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'playbook_steps' => 'array',
            'meta' => 'array',
            'due_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'last_followed_up_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            $task->setRawAttributes(
                Arr::only($task->getAttributes(), Schema::getColumnListing($task->getTable())),
                false,
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function adminOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_owner_id');
    }

    public function isOverdue(): bool
    {
        $dueAt = $this->getAttribute('due_at');

        return $this->completed_at === null
            && $dueAt instanceof Carbon
            && $dueAt->isPast();
    }

    public function markInProgress(?User $user = null): void
    {
        if ($user instanceof User) {
            $this->assigned_user_id = (string) $user->getKey();
        }

        $this->status = self::STATUS_IN_PROGRESS;
        $this->started_at ??= now();
        $this->save();
    }

    public function markCompleted(?User $user = null): void
    {
        if ($user instanceof User && blank($this->assigned_user_id)) {
            $this->assigned_user_id = (string) $user->getKey();
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now()->toDateTimeString();
        $this->save();
    }

    public function reassignTo(?User $user): void
    {
        $this->assigned_user_id = $user instanceof User ? (string) $user->getKey() : null;
        $this->save();
    }

    public function assignAdminOwner(?User $user): void
    {
        $this->admin_owner_id = $user instanceof User ? (string) $user->getKey() : null;
        $this->save();
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ((string) $this->priority) {
            self::PRIORITY_CRITICAL => 'Critica',
            self::PRIORITY_HIGH => 'Alta',
            default => 'Normal',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((string) $this->status) {
            self::STATUS_IN_PROGRESS => 'En curso',
            self::STATUS_COMPLETED => 'Completada',
            default => 'Abierta',
        };
    }

    public function getQueueLabelAttribute(): string
    {
        return match ((string) $this->queue) {
            'activation' => 'Activacion',
            'appointments' => 'Agenda',
            'growth' => 'Expansion',
            'retention' => 'Retencion',
            'billing' => 'Renovacion',
            default => 'Captacion',
        };
    }

    public function getFollowUpStatusLabelAttribute(): string
    {
        return match ((string) ($this->follow_up_status ?: self::FOLLOW_UP_NEW)) {
            self::FOLLOW_UP_ASSIGNED => 'Asignado',
            self::FOLLOW_UP_WAITING_CUSTOMER => 'Esperando cliente',
            self::FOLLOW_UP_SCHEDULED => 'Siguiente toque',
            self::FOLLOW_UP_ESCALATED => 'Escalado',
            self::FOLLOW_UP_CLOSED => 'Cerrado',
            default => 'Nuevo',
        };
    }
}
