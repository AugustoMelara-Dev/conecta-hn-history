<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant as Business;
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
 * EnhanceBusinessProfileJob - Asynchronous AI-Powered Business Profile Enrichment
 *
 * ARCHITECTURE:
 * - Queueable: Runs asynchronously via Laravel's queue system.
 * - Unique: Prevents duplicate processing of the same business.
 * - Fault Tolerant: 3 retries with exponential backoff.
 * - Observable: Comprehensive structured logging + moderation counters.
 * - Secure: Content moderation with automatic banning.
 * - Transactional: DB writes wrapped in explicit transaction.
 *
 * DATA PERSISTENCE:
 * - Original name preserved in original_name field.
 * - SEO keywords stored in theme_config JSON field (merged, not replaced).
 * - Risk analysis appended to admin_notes (never overwritten).
 *
 * DISPATCH:
 *   EnhanceBusinessProfileJob::dispatch($businessId);
 *
 * NOTE: Uses Tenant model internally (Business = Tenant in database schema)
 *
 * @package App\Jobs
 */
final class EnhanceBusinessProfileJob implements ShouldQueue, ShouldBeUnique
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

    public readonly int $businessId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $businessId)
    {
        $this->businessId = $businessId;
        $this->onQueue(self::QUEUE_NAME);
    }

    /**
     * Unique job identifier.
     */
    public function uniqueId(): string
    {
        return 'enhance-business-' . $this->businessId;
    }

    /**
     * Tags for Laravel Horizon monitoring.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['ai-enrichment', 'business:' . $this->businessId];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // JOB EXECUTION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Execute the job.
     *
     * @param AiProfileGeneratorService $aiService Injected via container
     * @throws Throwable Re-thrown to trigger retry mechanism
     */
    public function handle(AiProfileGeneratorService $aiService): void
    {
        // STEP 1: Retrieve business (bypass global scopes for soft-delete/multi-tenancy)
        // NOTE: Tenant model = Business in database schema
        $business = Business::withoutGlobalScopes()->find($this->businessId);

        if ($business === null) {
            Log::info('EnhanceBusinessProfileJob: Business not found', [
                'context' => 'ai_job_skip',
                'business_id' => $this->businessId,
                'reason' => 'not_found',
            ]);
            return;
        }

        // STEP 2: Eager load category (prevent N+1)
        $business->loadMissing('category');

        // STEP 3: Idempotency check
        if ($business->is_ai_enhanced === true) {
            Log::info('EnhanceBusinessProfileJob: Already enriched', [
                'context' => 'ai_job_skip',
                'business_id' => $this->businessId,
                'reason' => 'already_enriched',
            ]);
            return;
        }

        try {
            // STEP 4: Call AI service
            $enrichedData = $aiService->generate(
                $business->name ?? '',
                $business->description ?? '',
                $business->category?->name ?? 'General'
            );

            // STEP 5: Handle moderation outcome (wrapped in transaction)
            if ($enrichedData['status'] === 'flagged') {
                $this->handleFlaggedContent($business, $enrichedData);
            } else {
                $this->handleSuccessfulEnrichment($business, $enrichedData);
            }

        } catch (Throwable $e) {
            Log::error('EnhanceBusinessProfileJob: Enrichment failed', [
                'context' => 'ai_job_error',
                'business_id' => $this->businessId,
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
        Log::critical('EnhanceBusinessProfileJob: PERMANENTLY FAILED', [
            'context' => 'ai_job_permanent_failure',
            'business_id' => $this->businessId,
            'error' => $exception->getMessage(),
        ]);

        $business = Business::withoutGlobalScopes()->find($this->businessId);
        if ($business !== null) {
            DB::transaction(function () use ($business, $exception) {
                $business->update([
                    'admin_notes' => $this->appendToNotes(
                        $business->admin_notes,
                        '[' . now()->toIso8601String() . '] AI ENRICHMENT FAILED: ' . mb_substr($exception->getMessage(), 0, 200)
                    ),
                ]);
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // MODERATION HANDLERS
    // ═══════════════════════════════════════════════════════════════════════════════

    private function handleFlaggedContent(Business $business, array $enrichedData): void
    {
        $riskAnalysis = $enrichedData['risk_analysis'] ?? 'No analysis provided';
        $timestamp = now()->toIso8601String();

        DB::transaction(function () use ($business, $timestamp, $riskAnalysis) {
            $business->update([
                'status' => 'banned',
                'is_active' => false,
                'admin_notes' => $this->appendToNotes(
                    $business->admin_notes,
                    "[{$timestamp}] AI MODERATION: Content flagged.\nRisk: {$riskAnalysis}"
                ),
            ]);
        });

        Cache::increment(self::METRIC_BANS_TOTAL);

        Log::warning('EnhanceBusinessProfileJob: Business BANNED', [
            'context' => 'ai_moderation_ban',
            'business_id' => $this->businessId,
            'business_name' => $business->name,
            'risk_analysis' => $riskAnalysis,
            'total_bans' => Cache::get(self::METRIC_BANS_TOTAL, 0),
        ]);
    }

    private function handleSuccessfulEnrichment(Business $business, array $enrichedData): void
    {
        $riskAnalysis = $enrichedData['risk_analysis'] ?? 'No analysis provided';
        $timestamp = now()->toIso8601String();
        $keywordsCount = count($enrichedData['seo_keywords'] ?? []);

        // Preserve original name for potential rollback
        $originalName = $business->getOriginal('name') ?? $business->name;

        // CRITICAL: Merge SEO keywords into theme_config (preserve existing settings)
        // This prevents overwriting user's theme preferences
        $existingThemeConfig = $business->theme_config ?? [];
        $updatedThemeConfig = array_merge($existingThemeConfig, [
            'seo_keywords' => $enrichedData['seo_keywords'] ?? [],
        ]);

        DB::transaction(function () use ($business, $enrichedData, $updatedThemeConfig, $originalName, $timestamp, $keywordsCount, $riskAnalysis) {
            $business->update([
                // Preserve original name
                'original_name' => $originalName,
                // Enriched data
                'name' => $enrichedData['business_name_clean'],
                'slogan' => $enrichedData['tagline'],
                'description' => $enrichedData['full_description_html'],
                'about' => $enrichedData['short_description'],
                // JSON field with merged keywords
                'theme_config' => $updatedThemeConfig,
                'is_ai_enhanced' => true,
                // Incremental admin notes (never overwrite)
                'admin_notes' => $this->appendToNotes(
                    $business->admin_notes,
                    "[{$timestamp}] AI SUCCESS: {$keywordsCount} keywords.\nOriginal name: {$originalName}\nRisk: {$riskAnalysis}"
                ),
            ]);
        });

        Cache::increment(self::METRIC_SUCCESS_TOTAL);

        Log::info('EnhanceBusinessProfileJob: Success', [
            'context' => 'ai_enrichment_success',
            'business_id' => $this->businessId,
            'original_name' => $originalName,
            'new_name' => $enrichedData['business_name_clean'],
            'keywords_count' => $keywordsCount,
            'total_enrichments' => Cache::get(self::METRIC_SUCCESS_TOTAL, 0),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // UTILITIES
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Append new content to admin_notes without overwriting.
     */
    private function appendToNotes(?string $existing, string $new): string
    {
        if (empty($existing)) {
            return $new;
        }
        return $existing . "\n\n---\n\n" . $new;
    }
}
