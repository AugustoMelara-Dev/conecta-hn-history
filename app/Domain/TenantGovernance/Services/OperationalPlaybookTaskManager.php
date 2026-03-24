<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\Access\Enums\Role;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\OperationalPlaybookTask;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class OperationalPlaybookTaskManager
{
    public function __construct(
        private readonly BusinessSegmentResolver $segmentResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function syncForTrigger(Tenant $tenant, string $triggerKey, array $context = []): ?OperationalPlaybookTask
    {
        $profile = $this->resolveProfile($tenant, $triggerKey);

        if ($profile === []) {
            return null;
        }

        [$relatedType, $relatedId] = $this->resolveRelatedContext($context);
        $existingTask = $this->findOpenTask((string) $tenant->getKey(), $triggerKey, $relatedType, $relatedId);
        $isNewTask = ! $existingTask instanceof OperationalPlaybookTask;

        $task = $existingTask ?? new OperationalPlaybookTask;
        $assigneeId = $this->resolveAssigneeId($tenant, $profile);
        $title = $this->interpolate((string) ($profile['title'] ?? 'Playbook operativo'), $tenant, $context);
        $summary = $this->interpolate((string) ($profile['summary'] ?? ''), $tenant, $context);
        $crmSlaMinutes = max(
            15,
            (int) (($tenant->crm_sla_hours ?? 0) > 0
                ? ((int) $tenant->crm_sla_hours * 60)
                : (int) ($profile['sla_minutes'] ?? 60))
        );
        $steps = collect((array) ($profile['steps'] ?? []))
            ->filter(static fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();

        $task->forceFill([
            'tenant_id' => (string) $tenant->getKey(),
            'assigned_user_id' => $assigneeId,
            'admin_owner_id' => $existingTask?->admin_owner_id ?? (filled($tenant->crm_owner_id) ? (string) $tenant->crm_owner_id : null),
            'trigger_key' => $triggerKey,
            'queue' => (string) ($profile['queue'] ?? 'leads'),
            'route_key' => Arr::get($profile, 'route_key'),
            'segment_key' => $this->segmentResolver->resolveForTenant($tenant),
            'suggested_preset_key' => Arr::get($profile, 'preferred_presets.0'),
            'priority' => (string) ($profile['priority'] ?? OperationalPlaybookTask::PRIORITY_NORMAL),
            'status' => $existingTask?->status ?? OperationalPlaybookTask::STATUS_OPEN,
            'follow_up_status' => $existingTask?->follow_up_status
                ?? (filled($tenant->crm_owner_id) ? OperationalPlaybookTask::FOLLOW_UP_ASSIGNED : OperationalPlaybookTask::FOLLOW_UP_NEW),
            'sla_minutes' => $crmSlaMinutes,
            'title' => $title,
            'summary' => $summary,
            'playbook_steps' => $steps,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'meta' => $this->buildMeta($context),
            'due_at' => now()->addMinutes($crmSlaMinutes),
            'next_follow_up_at' => $existingTask?->next_follow_up_at ?? $tenant->crm_next_follow_up_at,
        ]);

        if (! $existingTask instanceof OperationalPlaybookTask) {
            $task->started_at = null;
            $task->completed_at = null;
        }

        $task->save();

        if ($isNewTask) {
            $tenant->logOperationalEvent(
                "Playbook operativo generado: {$title}",
                null,
                [
                    'trigger_key' => $triggerKey,
                    'queue' => (string) ($profile['queue'] ?? 'leads'),
                    'assigned_user_id' => $assigneeId,
                    'playbook_task_id' => (string) $task->getKey(),
                ],
            );
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function resolveAssigneeId(Tenant $tenant, array $profile): ?string
    {
        $memberships = TenantMembership::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereNotNull('accepted_at')
            ->with('user')
            ->get();

        foreach ((array) ($profile['preferred_presets'] ?? []) as $presetKey) {
            if (! is_string($presetKey) || $presetKey === '') {
                continue;
            }

            $match = $memberships->first(
                fn (TenantMembership $membership): bool => (string) $membership->getAttribute('preset_key') === $presetKey
            );

            if ($match instanceof TenantMembership) {
                return (string) $match->getAttribute('user_id');
            }
        }

        foreach ((array) ($profile['fallback_roles'] ?? []) as $roleValue) {
            $match = $memberships->first(function (TenantMembership $membership) use ($roleValue): bool {
                $role = $membership->getAttribute('role');
                $resolvedRole = $role instanceof Role ? $role->value : (string) $role;

                return $resolvedRole === (string) $roleValue;
            });

            if ($match instanceof TenantMembership) {
                return (string) $match->getAttribute('user_id');
            }
        }

        return filled($tenant->user_id) ? (string) $tenant->user_id : null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     * @param  array<string, mixed>  $context
     */
    private function resolveRelatedContext(array $context): array
    {
        $related = $context['related'] ?? null;

        if ($related instanceof Model) {
            return [$related::class, (string) $related->getKey()];
        }

        $relatedType = Arr::get($context, 'related_type');
        $relatedId = Arr::get($context, 'related_id');

        return [
            is_string($relatedType) && $relatedType !== '' ? $relatedType : null,
            is_scalar($relatedId) && (string) $relatedId !== '' ? (string) $relatedId : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProfile(Tenant $tenant, string $triggerKey): array
    {
        $base = config("operational-playbooks.triggers.{$triggerKey}", []);
        $segmentKey = $this->segmentResolver->resolveForTenant($tenant);
        $segmentOverride = config("operational-playbooks.segments.{$segmentKey}.{$triggerKey}", []);

        if (! is_array($base)) {
            return [];
        }

        return array_replace_recursive($base, is_array($segmentOverride) ? $segmentOverride : []);
    }

    private function findOpenTask(
        string $tenantId,
        string $triggerKey,
        ?string $relatedType,
        ?string $relatedId,
    ): ?OperationalPlaybookTask {
        $query = OperationalPlaybookTask::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('trigger_key', $triggerKey)
            ->whereIn('status', [
                OperationalPlaybookTask::STATUS_OPEN,
                OperationalPlaybookTask::STATUS_IN_PROGRESS,
            ]);

        if ($relatedType !== null && $relatedId !== null) {
            $query
                ->where('related_type', $relatedType)
                ->where('related_id', $relatedId);
        } else {
            $query
                ->whereNull('related_type')
                ->whereNull('related_id');
        }

        return $query->latest('created_at')->first();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildMeta(array $context): array
    {
        return collect($context)
            ->reject(static fn (mixed $value, string|int $key): bool => $key === 'related' || $value instanceof Model)
            ->map(static function (mixed $value): mixed {
                if (is_scalar($value) || $value === null) {
                    return $value;
                }

                if (is_array($value)) {
                    return $value;
                }

                return (string) $value;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function interpolate(string $template, Tenant $tenant, array $context): string
    {
        $replacements = [
            '{tenant_name}' => (string) ($tenant->name ?? 'tu negocio'),
            '{customer_name}' => (string) ($context['customer_name'] ?? 'este cliente'),
            '{service_name}' => (string) ($context['service_name'] ?? 'el servicio'),
            '{order_number}' => (string) ($context['order_number'] ?? 'la orden'),
            '{days_until_expiry}' => (string) ($context['days_until_expiry'] ?? 'pocos'),
            '{watch_metric}' => (string) ($context['watch_metric'] ?? 'la senal mas urgente'),
            '{watch_reason}' => (string) ($context['watch_reason'] ?? 'la cuenta pide atencion comercial'),
            '{activation_metric}' => (string) ($context['activation_metric'] ?? 'la palanca de activacion mas urgente'),
            '{activation_reason}' => (string) ($context['activation_reason'] ?? 'la cuenta necesita terminar su activacion'),
            '{expansion_metric}' => (string) ($context['expansion_metric'] ?? 'la siguiente oportunidad de expansion'),
            '{expansion_reason}' => (string) ($context['expansion_reason'] ?? 'la cuenta esta lista para crecer'),
            '{segment_label}' => (string) ($context['segment_label'] ?? 'su vertical'),
        ];

        return trim(Str::of(strtr($template, $replacements))->squish()->value());
    }
}
