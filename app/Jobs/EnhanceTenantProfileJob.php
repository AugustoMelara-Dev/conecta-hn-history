<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\AiProfileGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * EnhanceTenantProfileJob - Asynchronous AI-Powered Tenant Profile Enrichment
 *
 * ARCHITECTURE:
 * - Queueable: Runs asynchronously via Laravel's queue system.
 * - Unique: Prevents duplicate processing of the same tenant.
 * - Fault Tolerant: 3 retries with exponential backoff.
 * - Observable: Comprehensive structured logging + moderation counters.
 * - Secure: Content moderation with automatic banning.
 * - Transactional: DB writes wrapped in explicit transaction.
 *
 * DATA PERSISTENCE:
 * - Original name preserved in original_name field.
 * - SEO keywords stored in theme_config JSON field.
 * - Risk analysis appended to admin_notes (never overwritten).
 *
 * DISPATCH:
 *   EnhanceTenantProfileJob::dispatch($tenantId);
 */
final class EnhanceTenantProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // ═══════════════════════════════════════════════════════════════════════════════
    // JOB CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════════════

    /** Number of times the job may be attempted */
    public int $tries = 3;

    /** Exponential backoff delays in seconds */
    public array $backoff = [30, 60, 120];

    /** Maximum execution time (prevents queue blocking) */
    public int $timeout = 120;

    /** Unique lock duration in seconds */
    public int $uniqueFor = 3600;

    /** Queue name constant */
    private const QUEUE_NAME = 'ai-enrichment';

    /** Cache keys for metrics */
    private const METRIC_BANS_TOTAL = 'ai_moderation_bans_total';

    private const METRIC_SUCCESS_TOTAL = 'ai_enrichment_success_total';

    // ═══════════════════════════════════════════════════════════════════════════════
    // IMMUTABLE STATE (public readonly for proper queue serialization)
    // ═══════════════════════════════════════════════════════════════════════════════

    public readonly int $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->onQueue(self::QUEUE_NAME);
    }

    /**
     * Unique job identifier.
     */
    public function uniqueId(): string
    {
        return 'enhance-tenant-'.$this->tenantId;
    }

    /**
     * Tags for Laravel Horizon monitoring.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['ai-enrichment', 'tenant:'.$this->tenantId];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // JOB EXECUTION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Execute the job.
     *
     * @param  AiProfileGeneratorService  $aiService  Injected via container
     *
     * @throws Throwable Re-thrown to trigger retry mechanism
     */
    public function handle(AiProfileGeneratorService $aiService): void
    {
        // STEP 1: Retrieve tenant (bypass global scopes)
        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);
        if ($tenant) {
            // Log::info("DEBUG: Found tenant logic", ['id' => $tenant->id]);
        }

        if ($tenant === null) {
            Log::info('EnhanceTenantProfileJob: Tenant not found', [
                'context' => 'ai_job_skip',
                'tenant_id' => $this->tenantId,
                'reason' => 'not_found',
            ]);

            return;
        }

        // STEP 2: Eager load category (prevent N+1)
        $tenant->loadMissing('category');

        // STEP 3: Idempotency check
        if ((bool) $tenant->is_ai_enhanced === true) {
            Log::info('EnhanceTenantProfileJob: Already enriched', [
                'context' => 'ai_job_skip',
                'tenant_id' => $this->tenantId,
                'reason' => 'already_enriched',
            ]);

            return;
        }

        try {
            // STEP 4: Call AI service
            $enrichedData = $aiService->generate(
                $tenant->name ?? '',
                $tenant->description ?? '',
                $tenant->category?->name ?? 'General'
            );

            // STEP 5: Handle moderation outcome (wrapped in transaction)
            if ($enrichedData['status'] === 'flagged') {
                $this->handleFlaggedContent($tenant, $enrichedData);
            } else {
                $this->handleSuccessfulEnrichment($tenant, $enrichedData);
            }

        } catch (Throwable $e) {
            Log::error('EnhanceTenantProfileJob: Enrichment failed', [
                'context' => 'ai_job_error',
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle permanent job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('EnhanceTenantProfileJob: PERMANENTLY FAILED', [
            'context' => 'ai_job_permanent_failure',
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);

        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);
        if ($tenant !== null) {
            DB::transaction(function () use ($tenant, $exception) {
                $tenant->update([
                    'admin_notes' => $this->appendToNotes(
                        $tenant->admin_notes,
                        '['.now()->toIso8601String().'] AI ENRICHMENT FAILED: '.mb_substr($exception->getMessage(), 0, 200)
                    ),
                ]);
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // MODERATION HANDLERS
    // ═══════════════════════════════════════════════════════════════════════════════

    private function handleFlaggedContent(Tenant $tenant, array $enrichedData): void
    {
        $riskAnalysis = $enrichedData['risk_analysis'] ?? 'No analysis provided';
        $timestamp = now()->toIso8601String();

        // Wrap in transaction for atomicity
        DB::transaction(function () use ($tenant, $timestamp, $riskAnalysis) {
            $tenant->update([
                'status' => 'banned',
                'is_active' => false,
                'admin_notes' => $this->appendToNotes(
                    $tenant->admin_notes,
                    "[{$timestamp}] AI MODERATION: Content flagged.\nRisk: {$riskAnalysis}"
                ),
            ]);
        });

        // Increment ban counter for metrics/alerting
        Cache::increment(self::METRIC_BANS_TOTAL);

        Log::warning('EnhanceTenantProfileJob: Tenant BANNED', [
            'context' => 'ai_moderation_ban',
            'tenant_id' => $this->tenantId,
            'tenant_name' => $tenant->name,
            'risk_analysis' => $riskAnalysis,
            'total_bans' => Cache::get(self::METRIC_BANS_TOTAL, 0),
        ]);
    }

    private function handleSuccessfulEnrichment(Tenant $tenant, array $enrichedData): void
    {
        $riskAnalysis = $enrichedData['risk_analysis'] ?? 'No analysis provided';
        $timestamp = now()->toIso8601String();
        $keywordsCount = count($enrichedData['seo_keywords'] ?? []);

        // Preserve original name for potential rollback
        $originalName = $tenant->getOriginal('name') ?? $tenant->name;

        // Merge SEO keywords into theme_config (preserve existing settings)
        $updatedThemeConfig = array_merge(
            $tenant->theme_config ?? [],
            ['seo_keywords' => $enrichedData['seo_keywords'] ?? []]
        );

        // Wrap in transaction for atomicity
        DB::transaction(function () use ($tenant, $enrichedData, $updatedThemeConfig, $originalName, $timestamp, $keywordsCount, $riskAnalysis) {
            $tenant->update([
                // Preserve original name
                'original_name' => $originalName,
                // Enriched data
                'name' => $enrichedData['business_name_clean'],
                'slogan' => $enrichedData['tagline'],
                'description' => $enrichedData['full_description_html'],
                'about' => $enrichedData['short_description'],
                'theme_config' => $updatedThemeConfig,
                'is_ai_enhanced' => true,
                'admin_notes' => $this->appendToNotes(
                    $tenant->admin_notes,
                    "[{$timestamp}] AI SUCCESS: {$keywordsCount} keywords.\nOriginal name: {$originalName}\nRisk: {$riskAnalysis}"
                ),
            ]);
        });

        // Increment success counter for metrics
        Cache::increment(self::METRIC_SUCCESS_TOTAL);

        Log::info('EnhanceTenantProfileJob: Success', [
            'context' => 'ai_enrichment_success',
            'tenant_id' => $this->tenantId,
            'original_name' => $originalName,
            'new_name' => $enrichedData['business_name_clean'],
            'keywords_count' => $keywordsCount,
            'total_enrichments' => Cache::get(self::METRIC_SUCCESS_TOTAL, 0),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // UTILITIES
    // ═══════════════════════════════════════════════════════════════════════════════

    private function appendToNotes(?string $existing, string $new): string
    {
        if (empty($existing)) {
            return $new;
        }

        return $existing."\n\n---\n\n".$new;
    }
}
