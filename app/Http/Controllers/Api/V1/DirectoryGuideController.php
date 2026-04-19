<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Directory\Contracts\DirectoryGuideReader;
use App\Http\Controllers\Controller;
use App\Support\Directory\GuideQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DirectoryGuideController extends Controller
{
    public function __construct(
        private readonly DirectoryGuideReader $reader
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($validated['limit'] ?? 12);
        $items = $this->reader->searchPublishedTenants(
            search: $validated['search'] ?? null,
            citySlug: $validated['city'] ?? null,
            categorySlug: $validated['category'] ?? null,
            limit: $limit,
        );

        return response()->json([
            'data' => $items,
            'meta' => [
                'limit' => $limit,
                'count' => count($items),
                'degraded_to_search' => false,
                'ranking_mode' => 'sql',
                'reasoning_basis' => 'sql_directory_lookup',
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function guide(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $requestId = (string) ($request->header('x-request-id') ?: Str::uuid()->toString());
        $user = $request->user();

        if (! method_exists($user, 'isCustomer') || ! $user->isCustomer()) {
            return response()
                ->json([
                    'error_code' => 'DIRECTORY_GUIDE_CUSTOMER_ONLY',
                    'message' => 'La guia del directorio solo esta disponible para cuentas customer.',
                ], 403)
                ->header('x-request-id', $requestId);
        }

        $quotaStatus = GuideQuota::status($request);

        $validated = $request->validate([
            'budget' => ['nullable', 'numeric', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $budget = isset($validated['budget']) ? (float) $validated['budget'] : 0.0;
        $limit = (int) ($validated['limit'] ?? 8);
        $search = $validated['search'] ?? null;
        $city = $validated['city'] ?? null;
        $category = $validated['category'] ?? null;
        $hybridRerankEnabled = (bool) config('directory.guide.hybrid_rerank_enabled', false);
        $maxRerankCandidates = max(
            $limit,
            (int) config('directory.guide.max_rerank_candidates', 10)
        );

        if ($budget <= 0) {
            $fallback = $this->reader->searchPublishedTenants($search, $city, $category, $limit);

            return $this->respondGuide(
                request: $request,
                payload: [
                    'data' => $fallback,
                    'meta' => [
                        'budget' => null,
                        'count' => count($fallback),
                        'degraded_to_search' => true,
                        'degraded_reason' => 'missing_budget',
                        'ranking_mode' => 'fallback',
                        'reasoning_basis' => 'directory_search_fallback',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ],
                requestId: $requestId,
                startedAt: $startedAt,
                candidateCount: count($fallback),
                quotaStatus: $quotaStatus,
            );
        }

        try {
            $recommendations = $this->reader->recommendByBudget($budget, $city, $category, $search, $limit);

            if ($recommendations === []) {
                $fallback = $this->reader->searchPublishedTenants($search, $city, $category, $limit);

                return $this->respondGuide(
                    request: $request,
                    payload: [
                        'data' => $fallback,
                        'meta' => [
                            'budget' => round($budget, 2),
                            'count' => count($fallback),
                            'degraded_to_search' => true,
                            'degraded_reason' => 'no_budget_matches',
                            'ranking_mode' => 'fallback',
                            'reasoning_basis' => 'directory_search_fallback',
                            'generated_at' => now()->toIso8601String(),
                        ],
                    ],
                    requestId: $requestId,
                    startedAt: $startedAt,
                    candidateCount: count($fallback),
                    quotaStatus: $quotaStatus,
                );
            }

            $rankingMode = 'sql';
            $reasoningBasis = 'sql_budget_filter';
            $degradedReason = null;

            if ($hybridRerankEnabled && is_string($search) && trim($search) !== '') {
                $recommendations = array_slice($recommendations, 0, $maxRerankCandidates);

                try {
                    $recommendations = $this->reader->rerankRecommendations($recommendations, $search, $limit);
                    $rankingMode = 'hybrid';
                    $reasoningBasis = 'sql_filter_plus_embedding_rerank';
                } catch (\Throwable $exception) {
                    $degradedReason = $this->resolveDegradedReason($exception);
                    // Keep deterministic SQL recommendation list as graceful degradation.
                    $rankingMode = 'sql';
                    $reasoningBasis = 'sql_budget_filter';
                }
            }
        } catch (\Throwable $exception) {
            $degradedReason = $this->resolveDegradedReason($exception);

            if ($degradedReason === 'sql_recommendation_query_failed') {
                Log::warning('guide.sql_recommendation_query_failed', [
                    'request_id' => $requestId,
                    'message' => $exception->getMessage(),
                    'previous_message' => $exception->getPrevious()?->getMessage(),
                ]);
            }

            $fallback = $this->reader->searchPublishedTenants($search, $city, $category, $limit);

            return $this->respondGuide(
                request: $request,
                payload: [
                    'data' => $fallback,
                    'meta' => [
                        'budget' => round($budget, 2),
                        'count' => count($fallback),
                        'degraded_to_search' => true,
                        'degraded_reason' => $degradedReason,
                        'ranking_mode' => 'fallback',
                        'reasoning_basis' => 'directory_search_fallback',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ],
                requestId: $requestId,
                startedAt: $startedAt,
                candidateCount: count($fallback),
                quotaStatus: $quotaStatus,
            );
        }

        return $this->respondGuide(
            request: $request,
            payload: [
                'data' => $recommendations,
                'meta' => [
                    'budget' => round($budget, 2),
                    'count' => count($recommendations),
                    'degraded_to_search' => false,
                    'degraded_reason' => $degradedReason,
                    'ranking_mode' => $rankingMode,
                    'reasoning_basis' => $reasoningBasis,
                    'generated_at' => now()->toIso8601String(),
                ],
            ],
            requestId: $requestId,
            startedAt: $startedAt,
            candidateCount: count($recommendations),
            quotaStatus: $quotaStatus,
        );
    }

    private function respondGuide(
        Request $request,
        array $payload,
        string $requestId,
        float $startedAt,
        int $candidateCount,
        array $quotaStatus,
    ): JsonResponse {
        $meta = (array) ($payload['meta'] ?? []);
        $data = (array) ($payload['data'] ?? []);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $tenantCount = count($data);

        $payload['meta'] = array_merge($meta, $quotaStatus, [
            'request_id' => $requestId,
        ]);

        Log::info('guide.request.completed', [
            'event' => 'guide.request.completed',
            'request_id' => $requestId,
            'filters' => [
                'budget' => $request->query('budget'),
                'search' => $request->query('search'),
                'city' => $request->query('city'),
                'category' => $request->query('category'),
                'limit' => $request->query('limit'),
            ],
            'candidate_count' => $candidateCount,
            'ranking_mode' => $meta['ranking_mode'] ?? null,
            'reasoning_basis' => $meta['reasoning_basis'] ?? null,
            'degraded_reason' => $meta['degraded_reason'] ?? null,
            'degraded_to_search' => $meta['degraded_to_search'] ?? null,
            'latency_ms' => $latencyMs,
            'tenant_count' => $tenantCount,
            'quota_limit' => $quotaStatus['quota_limit'] ?? null,
            'quota_used' => $quotaStatus['quota_used'] ?? null,
            'quota_remaining' => $quotaStatus['quota_remaining'] ?? null,
            'quota_reset_at' => $quotaStatus['quota_reset_at'] ?? null,
        ]);

        $this->logGuideUsage($request, (string) ($meta['ranking_mode'] ?? 'sql'));

        return response()
            ->json($payload)
            ->header('x-request-id', $requestId);
    }

    private function logGuideUsage(Request $request, string $rankingMode): void
    {
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_string($userId) || $userId === '') {
            return;
        }

        try {
            DB::table('ai_usage_logs')->insert([
                'user_id' => $userId,
                'tenant_id' => null,
                'provider' => 'openai',
                'model' => (string) config('directory.guide.embedding_model', 'text-embedding-3-small'),
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('guide.usage_log.failed', [
                'user_id' => $userId,
                'ranking_mode' => $rankingMode,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveDegradedReason(\Throwable $exception): string
    {
        $reason = trim((string) $exception->getMessage());

        if (str_starts_with($reason, 'directory_guide_openai_embedding_failed_')) {
            $status = (int) str_replace('directory_guide_openai_embedding_failed_', '', $reason);

            if (in_array($status, [401, 403], true)) {
                return 'openai_auth_failed';
            }

            return 'openai_timeout';
        }

        return match ($reason) {
            'sql_recommendation_query_failed' => 'sql_recommendation_query_failed',
            'openai_auth_failed' => 'openai_auth_failed',
            'openai_timeout' => 'openai_timeout',
            'embedding_not_found' => 'embedding_not_found',
            'embedding_sync_stale' => 'embedding_sync_stale',
            'directory_guide_openai_key_missing',
            'directory_guide_openai_embedding_failed_401',
            'directory_guide_openai_embedding_failed_403' => 'openai_auth_failed',
            default => str_contains($reason, 'openai')
                ? 'openai_timeout'
                : 'sql_recommendation_query_failed',
        };
    }
}
