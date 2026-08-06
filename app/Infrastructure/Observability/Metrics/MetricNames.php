<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Metrics;

/**
 * MetricNames - Constantes de metric names registrados en MetricsRegistry.
 */
final class MetricNames
{
    public const AI_COST_TOTAL = 'ai_cost_total';
    public const AUTOMATION_RUNS_TOTAL = 'automation_runs_total';
    public const IMPORTS_TOTAL = 'imports_total';
    public const EXPORTS_TOTAL = 'exports_total';
    public const WORKFLOW_FAILURES_TOTAL = 'workflow_failures_total';
    public const CRM_CONTACTS_CREATED = 'crm_contacts_created';
    public const CRM_DEALS_WON = 'crm_deals_won';
    public const API_REQUESTS = 'api_requests';
    public const CACHE_HITS = 'cache_hits';
    public const CACHE_MISSES = 'cache_misses';
}
