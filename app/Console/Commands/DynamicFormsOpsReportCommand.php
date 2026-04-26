<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DynamicForms\DynamicModuleAdapterRegistry;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DynamicFormsOpsReportCommand extends Command
{
    protected $signature = 'dynamic-forms:ops-report
        {--hours=24 : Time window in hours}
        {--tenant= : Filter by tenant UUID}
        {--module= : Filter by module key}
        {--json : Emit JSON output}
        {--fail-on-alert : Exit with code 1 when alerts are present}';

    protected $description = 'Generate operational metrics and rollout status for dynamic forms and printing runtime.';

    public function handle(
        DynamicRuntimeFeatureManager $featureManager,
        DynamicModuleAdapterRegistry $adapterRegistry
    ): int {
        $requiredTables = [
            'entity_definitions',
            'dynamic_records',
            'dynamic_record_index_values',
            'dynamic_print_templates',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return $this->emitFailure("Missing required table: {$table}");
            }
        }

        $hours = max(1, (int) $this->option('hours'));
        $tenantId = $this->normalizeString($this->option('tenant'));
        $moduleKey = $this->normalizeString($this->option('module'));
        $since = now()->subHours($hours);

        $entitiesQuery = DB::table('entity_definitions');
        $this->applyEntityFilters($entitiesQuery, $tenantId, $moduleKey);

        $recordsQuery = DB::table('dynamic_records')
            ->join('entity_definitions', 'entity_definitions.id', '=', 'dynamic_records.entity_definition_id');
        $this->applyEntityFilters($recordsQuery, $tenantId, $moduleKey, 'dynamic_records.tenant_id');

        $indexesQuery = DB::table('dynamic_record_index_values')
            ->join('entity_definitions', 'entity_definitions.id', '=', 'dynamic_record_index_values.entity_definition_id');
        $this->applyEntityFilters($indexesQuery, $tenantId, $moduleKey, 'dynamic_record_index_values.tenant_id');

        $templatesQuery = DB::table('dynamic_print_templates')
            ->leftJoin('entity_definitions', 'entity_definitions.id', '=', 'dynamic_print_templates.entity_definition_id');
        if ($tenantId !== null) {
            $templatesQuery->where('dynamic_print_templates.tenant_id', $tenantId);
        }
        if ($moduleKey !== null) {
            $templatesQuery->where('entity_definitions.module_key', $moduleKey);
        }

        $totalEntities = (int) (clone $entitiesQuery)->count();
        $publishedEntities = (int) (clone $entitiesQuery)->where('status', 'published')->count();
        $draftEntities = (int) (clone $entitiesQuery)->where('status', 'draft')->count();
        $totalTemplates = (int) (clone $templatesQuery)->count();
        $publishedTemplates = (int) (clone $templatesQuery)->where('dynamic_print_templates.status', 'published')->count();
        $totalRecords = (int) (clone $recordsQuery)->count();
        $recentRecords = (int) (clone $recordsQuery)->where('dynamic_records.created_at', '>=', $since)->count();
        $indexRows = (int) (clone $indexesQuery)->count();

        $indexCoverage = $totalRecords > 0 ? round($indexRows / $totalRecords, 2) : 0.0;

        $recordsByModule = (clone $recordsQuery)
            ->selectRaw('entity_definitions.module_key as module_key, COUNT(*) as aggregate_count')
            ->groupBy('entity_definitions.module_key')
            ->pluck('aggregate_count', 'module_key')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $templateByScope = (clone $templatesQuery)
            ->selectRaw('COALESCE(dynamic_print_templates.owner_scope, ?) as owner_scope, COUNT(*) as aggregate_count', ['unknown'])
            ->groupBy('owner_scope')
            ->pluck('aggregate_count', 'owner_scope')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $adapters = collect($adapterRegistry->activeAdapters())
            ->map(static fn ($adapter): array => [
                'module' => $adapter->moduleKey(),
                'migration_enabled' => $adapter->isMigrationEnabled(),
                'legacy_resources' => $adapter->legacyResources(),
                'dynamic_entities' => $adapter->dynamicEntities(),
            ])
            ->values()
            ->all();

        $summary = [
            'window_hours' => $hours,
            'since' => $since->toIso8601String(),
            'tenant_filter' => $tenantId,
            'module_filter' => $moduleKey,
            'runtime' => [
                'enabled' => $featureManager->isEnabled(),
                'kill_switch_active' => $featureManager->isKillSwitchActive(),
                'admin_configurator_enabled' => $featureManager->isAdminConfiguratorEnabled(),
                'tenant_configurator_enabled' => $featureManager->isTenantConfiguratorEnabled(),
                'record_runtime_enabled' => $featureManager->isRecordRuntimeEnabled(),
                'print_runtime_enabled' => $featureManager->isPrintRuntimeEnabled(),
            ],
            'entities' => [
                'total' => $totalEntities,
                'published' => $publishedEntities,
                'draft' => $draftEntities,
            ],
            'templates' => [
                'total' => $totalTemplates,
                'published' => $publishedTemplates,
                'by_scope' => $templateByScope,
            ],
            'records' => [
                'total' => $totalRecords,
                'recent_window' => $recentRecords,
                'index_rows' => $indexRows,
                'index_coverage' => $indexCoverage,
                'by_module' => $recordsByModule,
            ],
            'module_adapters' => $adapters,
            'timestamp' => now()->toIso8601String(),
        ];

        $alerts = $this->detectAlerts($summary);
        $summary['alerts'] = $alerts;

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Dynamic Forms Ops Report (%dh)', $hours));
            $this->line(sprintf('- Runtime: enabled=%s kill_switch=%s', $this->boolWord($summary['runtime']['enabled']), $this->boolWord($summary['runtime']['kill_switch_active'])));
            $this->line(sprintf('- Entities: total=%d published=%d draft=%d', $totalEntities, $publishedEntities, $draftEntities));
            $this->line(sprintf('- Templates: total=%d published=%d', $totalTemplates, $publishedTemplates));
            $this->line(sprintf('- Records: total=%d recent=%d index_rows=%d coverage=%.2f', $totalRecords, $recentRecords, $indexRows, $indexCoverage));
            $this->line(sprintf('- Module adapters activos: %d', count($adapters)));

            if ($alerts === []) {
                $this->line('ALERTS: none');
            } else {
                foreach ($alerts as $alert) {
                    $this->warn('ALERT: '.$alert);
                }
            }
        }

        if ((bool) $this->option('fail-on-alert') && $alerts !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function emitFailure(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'failed',
                'error' => $message,
                'timestamp' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }

    private function boolWord(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function applyEntityFilters(Builder $query, ?string $tenantId, ?string $moduleKey, string $tenantColumn = 'entity_definitions.tenant_id'): void
    {
        if ($tenantId !== null) {
            $query->where($tenantColumn, $tenantId);
        }

        if ($moduleKey !== null) {
            $query->where('entity_definitions.module_key', $moduleKey);
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    private function detectAlerts(array $summary): array
    {
        $alerts = [];

        $runtime = (array) ($summary['runtime'] ?? []);
        $records = (array) ($summary['records'] ?? []);
        $entities = (array) ($summary['entities'] ?? []);
        $templates = (array) ($summary['templates'] ?? []);

        if ((bool) ($runtime['kill_switch_active'] ?? false)) {
            $alerts[] = 'kill_switch_active';
        }

        if ((int) ($entities['published'] ?? 0) === 0 && (int) ($entities['total'] ?? 0) > 0) {
            $alerts[] = 'no_published_entities';
        }

        if ((int) ($templates['published'] ?? 0) === 0 && (int) ($templates['total'] ?? 0) > 0) {
            $alerts[] = 'no_published_templates';
        }

        if ((int) ($records['total'] ?? 0) > 0 && (float) ($records['index_coverage'] ?? 0.0) < 0.5) {
            $alerts[] = 'low_index_coverage';
        }

        return $alerts;
    }
}
