<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicConfigAuditInterface;
use App\Events\DynamicForms\SchemaPublished;
use App\Events\DynamicForms\TemplatePublished;
use App\Events\DynamicForms\TenantBlueprintCloned;
use App\Models\Dynamic\DynamicConfigAudit;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\DynamicPrintTemplateVersion;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\SchemaVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DynamicConfigAuditService implements DynamicConfigAuditInterface
{
    public function record(object $event): void
    {
        if (! Schema::hasTable('dynamic_config_audits')) {
            return;
        }

        if ($event instanceof SchemaPublished) {
            $this->recordSchemaPublished($event);

            return;
        }

        if ($event instanceof TemplatePublished) {
            $this->recordTemplatePublished($event);

            return;
        }

        if ($event instanceof TenantBlueprintCloned) {
            $this->recordTenantBlueprintCloned($event);
        }
    }

    private function recordSchemaPublished(SchemaPublished $event): void
    {
        if (! Schema::hasTable('entity_definitions') || ! Schema::hasTable('schema_versions')) {
            return;
        }

        $entity = EntityDefinition::query()->find($event->entityDefinitionId);
        if (! $entity instanceof EntityDefinition) {
            return;
        }

        $afterVersion = SchemaVersion::query()
            ->where('entity_definition_id', $entity->getKey())
            ->where('version_number', $event->versionNumber)
            ->first();

        $beforeVersion = SchemaVersion::query()
            ->where('entity_definition_id', $entity->getKey())
            ->where('version_number', '<', $event->versionNumber)
            ->orderByDesc('version_number')
            ->first();

        $beforeJson = is_array($beforeVersion?->schema_json) ? $beforeVersion->schema_json : null;
        $afterJson = is_array($afterVersion?->schema_json) ? $afterVersion->schema_json : null;
        $jsonPatch = $this->buildJsonPatch($beforeJson, $afterJson);

        $this->persistAudit(
            actionKey: 'schema_published',
            resourceType: 'entity_definition',
            resourceId: (string) $entity->getKey(),
            eventName: $event::class,
            actorId: $event->publishedBy,
            tenantId: $event->tenantId ?: ($entity->tenant_id ? (string) $entity->tenant_id : null),
            beforeJson: $beforeJson,
            afterJson: $afterJson,
            jsonPatch: $jsonPatch,
            metadata: [
                'entity_key' => $event->entityKey,
                'version_number' => $event->versionNumber,
                'previous_version_number' => $beforeVersion?->version_number,
                'diff_summary' => $this->buildDiffSummary($jsonPatch),
            ],
            subject: $entity,
        );
    }

    private function recordTemplatePublished(TemplatePublished $event): void
    {
        if (! Schema::hasTable('dynamic_print_templates') || ! Schema::hasTable('dynamic_print_template_versions')) {
            return;
        }

        $template = DynamicPrintTemplate::query()->find($event->templateId);
        if (! $template instanceof DynamicPrintTemplate) {
            return;
        }

        $afterVersion = DynamicPrintTemplateVersion::query()
            ->where('dynamic_print_template_id', $template->getKey())
            ->where('version_number', $event->versionNumber)
            ->first();

        $beforeVersion = DynamicPrintTemplateVersion::query()
            ->where('dynamic_print_template_id', $template->getKey())
            ->where('version_number', '<', $event->versionNumber)
            ->orderByDesc('version_number')
            ->first();

        $beforeJson = is_array($beforeVersion?->blocks_json) ? $beforeVersion->blocks_json : null;
        $afterJson = is_array($afterVersion?->blocks_json) ? $afterVersion->blocks_json : null;
        $jsonPatch = $this->buildJsonPatch($beforeJson, $afterJson);

        $this->persistAudit(
            actionKey: 'template_published',
            resourceType: 'print_template',
            resourceId: (string) $template->getKey(),
            eventName: $event::class,
            actorId: $event->publishedBy,
            tenantId: $event->tenantId ?: ($template->tenant_id ? (string) $template->tenant_id : null),
            beforeJson: $beforeJson,
            afterJson: $afterJson,
            jsonPatch: $jsonPatch,
            metadata: [
                'template_key' => $event->templateKey,
                'version_number' => $event->versionNumber,
                'previous_version_number' => $beforeVersion?->version_number,
                'diff_summary' => $this->buildDiffSummary($jsonPatch),
            ],
            subject: $template,
        );
    }

    private function recordTenantBlueprintCloned(TenantBlueprintCloned $event): void
    {
        if ($event->blueprintType === 'entity_definition') {
            if (! Schema::hasTable('entity_definitions')) {
                return;
            }

            $source = EntityDefinition::query()->with(['fields', 'layouts'])->find($event->sourceId);
            $target = EntityDefinition::query()->with(['fields', 'layouts'])->find($event->targetId);

            $beforeJson = $source instanceof EntityDefinition ? $this->entitySnapshot($source) : null;
            $afterJson = $target instanceof EntityDefinition ? $this->entitySnapshot($target) : null;
            $jsonPatch = $this->buildJsonPatch($beforeJson, $afterJson);

            $this->persistAudit(
                actionKey: 'tenant_blueprint_cloned',
                resourceType: 'entity_definition',
                resourceId: $event->targetId,
                eventName: $event::class,
                actorId: $event->actorId,
                tenantId: $event->tenantId,
                beforeJson: $beforeJson,
                afterJson: $afterJson,
                jsonPatch: $jsonPatch,
                metadata: [
                    'blueprint_type' => $event->blueprintType,
                    'source_id' => $event->sourceId,
                    'target_id' => $event->targetId,
                    'diff_summary' => $this->buildDiffSummary($jsonPatch),
                ],
                subject: $target instanceof EntityDefinition ? $target : null,
            );

            return;
        }

        if ($event->blueprintType === 'print_template') {
            if (! Schema::hasTable('dynamic_print_templates')) {
                return;
            }

            $source = DynamicPrintTemplate::query()->find($event->sourceId);
            $target = DynamicPrintTemplate::query()->find($event->targetId);

            $beforeJson = $source instanceof DynamicPrintTemplate ? $this->templateSnapshot($source) : null;
            $afterJson = $target instanceof DynamicPrintTemplate ? $this->templateSnapshot($target) : null;
            $jsonPatch = $this->buildJsonPatch($beforeJson, $afterJson);

            $this->persistAudit(
                actionKey: 'tenant_blueprint_cloned',
                resourceType: 'print_template',
                resourceId: $event->targetId,
                eventName: $event::class,
                actorId: $event->actorId,
                tenantId: $event->tenantId,
                beforeJson: $beforeJson,
                afterJson: $afterJson,
                jsonPatch: $jsonPatch,
                metadata: [
                    'blueprint_type' => $event->blueprintType,
                    'source_id' => $event->sourceId,
                    'target_id' => $event->targetId,
                    'diff_summary' => $this->buildDiffSummary($jsonPatch),
                ],
                subject: $target instanceof DynamicPrintTemplate ? $target : null,
            );
        }
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $beforeJson
     * @param  array<string, mixed>|list<mixed>|null  $afterJson
     * @param  array<int, array<string, mixed>>  $jsonPatch
     * @param  array<string, mixed>  $metadata
     */
    private function persistAudit(
        string $actionKey,
        string $resourceType,
        ?string $resourceId,
        string $eventName,
        ?string $actorId,
        ?string $tenantId,
        ?array $beforeJson,
        ?array $afterJson,
        array $jsonPatch,
        array $metadata,
        ?Model $subject = null,
    ): void {
        ['request_id' => $requestId, 'ip_hash' => $ipHash] = $this->resolveRequestContext();
        $resolvedTenantId = $this->resolveTenantForeignKey($tenantId);
        $resolvedActorId = $this->resolveActorForeignKey($actorId);

        if ($resolvedTenantId === null && is_string($tenantId) && $tenantId !== '') {
            $metadata['tenant_id_unresolved'] = $tenantId;
        }

        if ($resolvedActorId === null && is_string($actorId) && $actorId !== '') {
            $metadata['actor_id_unresolved'] = $actorId;
        }

        $audit = DynamicConfigAudit::query()->create([
            'tenant_id' => $resolvedTenantId,
            'actor_id' => $resolvedActorId,
            'action_key' => $actionKey,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'event_name' => $eventName,
            'request_id' => $requestId,
            'ip_hash' => $ipHash,
            'before_json' => $beforeJson,
            'after_json' => $afterJson,
            'json_patch' => $jsonPatch,
            'metadata_json' => $metadata,
        ]);

        $this->mirrorToActivityLog($audit, $subject);
    }

    /**
     * @return array{request_id:?string,ip_hash:?string}
     */
    private function resolveRequestContext(): array
    {
        if (! app()->bound('request')) {
            return ['request_id' => null, 'ip_hash' => null];
        }

        $request = request();
        if (! $request instanceof Request) {
            return ['request_id' => null, 'ip_hash' => null];
        }

        $requestId = (string) ($request->header('x-request-id') ?: $request->attributes->get('request_id') ?: '');
        $requestId = $requestId !== '' ? $requestId : null;

        $ip = $request->ip();
        $ipHash = is_string($ip) && $ip !== '' ? hash('sha256', $ip) : null;

        return [
            'request_id' => $requestId,
            'ip_hash' => $ipHash,
        ];
    }

    private function mirrorToActivityLog(DynamicConfigAudit $audit, ?Model $subject = null): void
    {
        $tableName = (string) config('activitylog.table_name', 'activity_log');
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $subjectType = $subject instanceof Model ? $subject::class : null;
        $subjectId = $subject?->getKey();

        $causerType = null;
        $causerId = null;
        if (is_string($audit->actor_id) && $audit->actor_id !== '') {
            $causerType = User::class;
            $causerId = $audit->actor_id;
        }

        $payload = [
            'log_name' => 'dynamic_forms',
            'description' => 'dynamic_forms.'.$audit->action_key,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_type' => $causerType,
            'causer_id' => $causerId,
            'properties' => json_encode([
                'dynamic_config_audit_id' => $audit->getKey(),
                'tenant_id' => $audit->tenant_id,
                'resource_type' => $audit->resource_type,
                'resource_id' => $audit->resource_id,
                'request_id' => $audit->request_id,
                'ip_hash' => $audit->ip_hash,
                'before_json' => $audit->before_json,
                'after_json' => $audit->after_json,
                'json_patch' => $audit->json_patch,
                'metadata_json' => $audit->metadata_json,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn($tableName, 'event')) {
            $payload['event'] = $audit->action_key;
        }

        if (Schema::hasColumn($tableName, 'batch_uuid')) {
            $payload['batch_uuid'] = null;
        }

        DB::table($tableName)->insert($payload);
    }

    private function resolveTenantForeignKey(?string $tenantId): ?string
    {
        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return Tenant::query()->whereKey($tenantId)->exists() ? $tenantId : null;
    }

    private function resolveActorForeignKey(?string $actorId): ?string
    {
        if (! is_string($actorId) || $actorId === '') {
            return null;
        }

        return User::query()->whereKey($actorId)->exists() ? $actorId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function entitySnapshot(EntityDefinition $entity): array
    {
        $entity->loadMissing(['fields', 'layouts']);

        return [
            'id' => (string) $entity->getKey(),
            'tenant_id' => $entity->tenant_id,
            'source_entity_definition_id' => $entity->source_entity_definition_id,
            'owner_scope' => $entity->owner_scope,
            'module_key' => $entity->module_key,
            'entity_key' => $entity->entity_key,
            'name' => $entity->name,
            'status' => $entity->status,
            'allow_tenant_overrides' => (bool) $entity->allow_tenant_overrides,
            'is_locked' => (bool) $entity->is_locked,
            'active_schema_version_id' => $entity->active_schema_version_id,
            'fields' => $entity->fields
                ->sortBy('sort_order')
                ->values()
                ->map(static fn ($field): array => [
                    'field_key' => $field->field_key,
                    'field_type' => $field->field_type,
                    'label' => $field->label,
                    'is_required' => (bool) $field->is_required,
                    'storage_mode' => $field->storage_mode,
                    'is_indexed' => (bool) $field->is_indexed,
                    'sort_order' => (int) $field->sort_order,
                    'status' => $field->status,
                ])
                ->all(),
            'layouts' => $entity->layouts
                ->values()
                ->map(static fn ($layout): array => [
                    'layout_key' => $layout->layout_key,
                    'layout_json' => $layout->layout_json,
                    'status' => $layout->status,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSnapshot(DynamicPrintTemplate $template): array
    {
        return [
            'id' => (string) $template->getKey(),
            'tenant_id' => $template->tenant_id,
            'entity_definition_id' => $template->entity_definition_id,
            'source_print_template_id' => $template->source_print_template_id,
            'owner_scope' => $template->owner_scope,
            'template_key' => $template->template_key,
            'action_key' => $template->action_key,
            'name' => $template->name,
            'status' => $template->status,
            'is_locked' => (bool) $template->is_locked,
            'active_version_id' => $template->active_version_id,
            'blocks_json' => is_array($template->blocks_json) ? $template->blocks_json : [],
            'placeholder_whitelist' => is_array($template->placeholder_whitelist) ? $template->placeholder_whitelist : [],
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $before
     * @param  array<string, mixed>|list<mixed>|null  $after
     * @return array<int, array<string, mixed>>
     */
    private function buildJsonPatch(null|array $before, null|array $after): array
    {
        return $this->diffValues($before, $after, '');
    }

    /**
     * @param  mixed  $before
     * @param  mixed  $after
     * @return array<int, array<string, mixed>>
     */
    private function diffValues(mixed $before, mixed $after, string $path): array
    {
        if ($before === $after) {
            return [];
        }

        $pointer = $this->jsonPointer($path);

        if (is_array($before) && is_array($after)) {
            if (array_is_list($before) || array_is_list($after)) {
                return [[
                    'op' => 'replace',
                    'path' => $pointer,
                    'value' => $after,
                ]];
            }

            $patch = [];
            $beforeKeys = array_keys($before);
            $afterKeys = array_keys($after);

            foreach (array_diff($beforeKeys, $afterKeys) as $removedKey) {
                $patch[] = [
                    'op' => 'remove',
                    'path' => $this->jsonPointer($this->joinPath($path, (string) $removedKey)),
                ];
            }

            foreach ($after as $key => $value) {
                $nextPath = $this->joinPath($path, (string) $key);
                if (! array_key_exists($key, $before)) {
                    $patch[] = [
                        'op' => 'add',
                        'path' => $this->jsonPointer($nextPath),
                        'value' => $value,
                    ];

                    continue;
                }

                $patch = [...$patch, ...$this->diffValues($before[$key], $value, $nextPath)];
            }

            return $patch;
        }

        if ($after === null) {
            return [[
                'op' => 'remove',
                'path' => $pointer,
            ]];
        }

        if ($before === null) {
            return [[
                'op' => 'add',
                'path' => $pointer,
                'value' => $after,
            ]];
        }

        return [[
            'op' => 'replace',
            'path' => $pointer,
            'value' => $after,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $patch
     * @return array{operations:int,add:int,replace:int,remove:int}
     */
    private function buildDiffSummary(array $patch): array
    {
        $summary = [
            'operations' => count($patch),
            'add' => 0,
            'replace' => 0,
            'remove' => 0,
        ];

        foreach ($patch as $operation) {
            $op = (string) ($operation['op'] ?? '');
            if (array_key_exists($op, $summary)) {
                $summary[$op]++;
            }
        }

        return $summary;
    }

    private function joinPath(string $path, string $segment): string
    {
        if ($path === '') {
            return $segment;
        }

        return $path.'/'.$segment;
    }

    private function jsonPointer(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        return '/'.collect(explode('/', $path))
            ->map(static fn (string $token): string => str_replace(['~', '/'], ['~0', '~1'], $token))
            ->implode('/');
    }
}
