<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Directory\Contracts\DirectoryGuideReader;
use App\Http\Controllers\Controller;
use App\Infrastructure\Directory\DirectoryGuideQdrantRetriever;
use App\Support\Directory\GuideQuota;
use App\Support\Directory\GuideRequestTelemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DirectoryGuideController extends Controller
{
    public function __construct(
        private readonly DirectoryGuideReader $reader,
        private readonly DirectoryGuideQdrantRetriever $qdrantRetriever
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
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'search' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $budget = isset($validated['budget']) ? (float) $validated['budget'] : 0.0;
        $currency = strtoupper((string) ($validated['currency'] ?? 'HNL'));
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
                        'budget_currency' => $currency,
                        'budget_typed' => null,
                        'count' => count($fallback),
                        'degraded_to_search' => true,
                        'degraded_reason' => 'missing_budget',
                        'ranking_mode' => 'fallback',
                        'reasoning_basis' => 'directory_search_fallback',
                        'ranking_explanation' => $this->rankingExplanation('fallback', 'directory_search_fallback', 'missing_budget'),
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
                            'budget_currency' => $currency,
                            'budget_typed' => [
                                'amount' => round($budget, 2),
                                'currency' => $currency,
                            ],
                            'count' => count($fallback),
                            'degraded_to_search' => true,
                            'degraded_reason' => 'no_budget_matches',
                            'ranking_mode' => 'fallback',
                            'reasoning_basis' => 'directory_search_fallback',
                            'ranking_explanation' => $this->rankingExplanation('fallback', 'directory_search_fallback', 'no_budget_matches'),
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
                        'budget_currency' => $currency,
                        'budget_typed' => [
                            'amount' => round($budget, 2),
                            'currency' => $currency,
                        ],
                        'count' => count($fallback),
                        'degraded_to_search' => true,
                        'degraded_reason' => $degradedReason,
                        'ranking_mode' => 'fallback',
                        'reasoning_basis' => 'directory_search_fallback',
                        'ranking_explanation' => $this->rankingExplanation('fallback', 'directory_search_fallback', $degradedReason),
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
                    'budget_currency' => $currency,
                    'budget_typed' => [
                        'amount' => round($budget, 2),
                        'currency' => $currency,
                    ],
                    'count' => count($recommendations),
                    'degraded_to_search' => false,
                    'degraded_reason' => $degradedReason,
                    'ranking_mode' => $rankingMode,
                    'reasoning_basis' => $reasoningBasis,
                    'ranking_explanation' => $this->rankingExplanation($rankingMode, $reasoningBasis, $degradedReason),
                    'generated_at' => now()->toIso8601String(),
                ],
            ],
            requestId: $requestId,
            startedAt: $startedAt,
            candidateCount: count($recommendations),
            quotaStatus: $quotaStatus,
        );
    }

    public function chat(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $requestId = (string) ($request->header('x-request-id') ?: Str::uuid()->toString());
        $traceId = (string) ($request->header('x-trace-id') ?: Str::uuid()->toString());
        $user = $request->user();

        if (! method_exists($user, 'isCustomer') || ! $user->isCustomer()) {
            return response()
                ->json([
                    'error_code' => 'DIRECTORY_GUIDE_CUSTOMER_ONLY',
                    'message' => 'La guia del directorio solo esta disponible para cuentas customer.',
                ], 403)
                ->header('x-request-id', $requestId)
                ->header('x-trace-id', $traceId);
        }

        $quotaStatus = GuideQuota::status($request);
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:240'],
            'budget_amount' => ['nullable', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $query = trim((string) ($validated['query'] ?? ''));
        $budgetAmount = isset($validated['budget_amount']) ? (float) $validated['budget_amount'] : 0.0;
        $currency = strtoupper((string) ($validated['currency'] ?? 'HNL'));
        $city = $validated['city'] ?? null;
        $category = $validated['category'] ?? null;
        $limit = (int) ($validated['limit'] ?? 8);
        $hybridRerankEnabled = (bool) config('directory.guide.hybrid_rerank_enabled', false);
        $maxRerankCandidates = max(
            $limit,
            (int) config('directory.guide.max_rerank_candidates', 10)
        );
        $qdrantHints = [];
        $qdrantApplied = false;
        $degradedReason = null;
        $queryRelaxed = false;

        if ($budgetAmount <= 0) {
            $fallback = $this->reader->searchPublishedTenants($query, $city, $category, $limit);

            return $this->respondGuide(
                request: $request,
                payload: [
                    'data' => $fallback,
                    'meta' => [
                        'query' => $query,
                        'budget' => null,
                        'budget_currency' => $currency,
                        'budget_typed' => null,
                        'count' => count($fallback),
                        'degraded_to_search' => true,
                        'degraded_reason' => 'missing_budget',
                        'ranking_mode' => 'fallback',
                        'reasoning_basis' => 'directory_search_fallback',
                        'ranking_explanation' => $this->rankingExplanation('fallback', 'directory_search_fallback', 'missing_budget'),
                        'generated_at' => now()->toIso8601String(),
                        'chat_answer' => $this->chatAnswer($fallback, null, $currency),
                    ],
                ],
                requestId: $requestId,
                startedAt: $startedAt,
                candidateCount: count($fallback),
                quotaStatus: $quotaStatus,
                traceId: $traceId,
                endpoint: 'guide_chat',
            );
        }

        try {
            try {
                $qdrantHints = $this->qdrantRetriever->resolveProductHints(
                    query: $query,
                    budgetAmount: $budgetAmount,
                    currency: $currency,
                    citySlug: $city,
                    categorySlug: $category,
                    limit: $limit,
                );
                $qdrantApplied = $qdrantHints !== [];
            } catch (\Throwable $qdrantException) {
                $degradedReason = 'qdrant_unavailable';
                Log::warning('guide.chat.qdrant_unavailable', [
                    'request_id' => $requestId,
                    'trace_id' => $traceId,
                    'error' => $qdrantException->getMessage(),
                ]);
            }

            $recommendations = $this->reader->recommendByBudget(
                budget: $budgetAmount,
                citySlug: $city,
                categorySlug: $category,
                search: $qdrantApplied ? null : $query,
                limit: $limit,
                hintProductIds: $qdrantHints,
            );

            if ($recommendations === [] && ! $qdrantApplied && $query !== '') {
                $recommendations = $this->reader->recommendByBudget(
                    budget: $budgetAmount,
                    citySlug: $city,
                    categorySlug: $category,
                    search: null,
                    limit: $limit,
                    hintProductIds: [],
                );

                if ($recommendations !== []) {
                    $queryRelaxed = true;
                    $degradedReason = 'directory_query_relaxed';
                }
            }

            if ($recommendations === []) {
                $fallback = $this->reader->searchPublishedTenants($query, $city, $category, $limit);

                return $this->respondGuide(
                    request: $request,
                    payload: [
                        'data' => $fallback,
                        'meta' => [
                            'query' => $query,
                            'budget' => round($budgetAmount, 2),
                            'budget_currency' => $currency,
                            'budget_typed' => [
                                'amount' => round($budgetAmount, 2),
                                'currency' => $currency,
                            ],
                            'count' => count($fallback),
                            'degraded_to_search' => true,
                            'degraded_reason' => 'no_budget_matches',
                            'ranking_mode' => 'fallback',
                            'reasoning_basis' => 'directory_search_fallback',
                            'qdrant_applied' => $qdrantApplied,
                            'qdrant_candidates' => count($qdrantHints),
                            'ranking_explanation' => $this->rankingExplanation('fallback', 'directory_search_fallback', 'no_budget_matches'),
                            'generated_at' => now()->toIso8601String(),
                            'chat_answer' => $this->chatAnswer($fallback, $budgetAmount, $currency),
                        ],
                    ],
                    requestId: $requestId,
                    startedAt: $startedAt,
                    candidateCount: count($fallback),
                    quotaStatus: $quotaStatus,
                    traceId: $traceId,
                    endpoint: 'guide_chat',
                );
            }

            $rankingMode = $qdrantApplied ? 'hybrid' : 'sql';
            $reasoningBasis = $qdrantApplied
                ? 'qdrant_semantic_retrieval_plus_sql_budget_filter'
                : 'sql_budget_filter';

            if ($hybridRerankEnabled && $query !== '') {
                $recommendations = array_slice($recommendations, 0, $maxRerankCandidates);

                try {
                    $recommendations = $this->reader->rerankRecommendations($recommendations, $query, $limit);
                    $rankingMode = 'hybrid';
                    $reasoningBasis = $qdrantApplied
                        ? 'qdrant_semantic_retrieval_plus_embedding_rerank'
                        : 'sql_filter_plus_embedding_rerank';
                } catch (\Throwable $exception) {
                    if ($degradedReason === null) {
                        $degradedReason = $this->resolveDegradedReason($exception);
                    }
                }
            }
        } catch (\Throwable $exception) {
            if ($degradedReason === null) {
                $degradedReason = $this->resolveDegradedReason($exception);
            }

            $fallback = $this->reader->searchPublishedTenants($query, $city, $category, $limit);

            return $this->respondGuide(
                request: $request,
                payload: [
                    'data' => $fallback,
                    'meta' => [
                        'query' => $query,
                        'budget' => round($budgetAmount, 2),
                        'budget_currency' => $currency,
                        'budget_typed' => [
                            'amount' => round($budgetAmount, 2),
                            'currency' => $currency,
                        ],
                        'count' => count($fallback),
                        'degraded_to_search' => true,
                        'degraded_reason' => $degradedReason,
                        'ranking_mode' => 'fallback',
                        'reasoning_basis' => 'directory_search_fallback',
                        'qdrant_applied' => $qdrantApplied,
                        'qdrant_candidates' => count($qdrantHints),
                        'ranking_explanation' => $this->rankingExplanation('fallback', 'directory_search_fallback', $degradedReason),
                        'generated_at' => now()->toIso8601String(),
                        'chat_answer' => $this->chatAnswer($fallback, $budgetAmount, $currency),
                    ],
                ],
                requestId: $requestId,
                startedAt: $startedAt,
                candidateCount: count($fallback),
                quotaStatus: $quotaStatus,
                traceId: $traceId,
                endpoint: 'guide_chat',
            );
        }

        return $this->respondGuide(
            request: $request,
            payload: [
                'data' => $recommendations,
                'meta' => [
                    'query' => $query,
                    'budget' => round($budgetAmount, 2),
                    'budget_currency' => $currency,
                    'budget_typed' => [
                        'amount' => round($budgetAmount, 2),
                        'currency' => $currency,
                    ],
                    'count' => count($recommendations),
                    'degraded_to_search' => false,
                    'degraded_reason' => $degradedReason,
                    'ranking_mode' => $rankingMode,
                    'reasoning_basis' => $reasoningBasis,
                    'qdrant_applied' => $qdrantApplied,
                    'qdrant_candidates' => count($qdrantHints),
                    'query_relaxed' => $queryRelaxed,
                    'ranking_explanation' => $this->rankingExplanation($rankingMode, $reasoningBasis, $degradedReason),
                    'generated_at' => now()->toIso8601String(),
                    'chat_answer' => $this->chatAnswer($recommendations, $budgetAmount, $currency),
                ],
            ],
            requestId: $requestId,
            startedAt: $startedAt,
            candidateCount: count($recommendations),
            quotaStatus: $quotaStatus,
            traceId: $traceId,
            endpoint: 'guide_chat',
        );
    }

    private function respondGuide(
        Request $request,
        array $payload,
        string $requestId,
        float $startedAt,
        int $candidateCount,
        array $quotaStatus,
        ?string $traceId = null,
        string $endpoint = 'guide',
    ): JsonResponse {
        $meta = (array) ($payload['meta'] ?? []);
        $data = (array) ($payload['data'] ?? []);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $tenantCount = count($data);

        $payload['meta'] = array_merge($meta, $quotaStatus, [
            'request_id' => $requestId,
            'trace_id' => $traceId ?? $requestId,
            'diagnostic' => $this->guideDiagnostic($meta, $candidateCount, $traceId ?? $requestId),
        ]);

        Log::info('guide.request.completed', [
            'event' => 'guide.request.completed',
            'request_id' => $requestId,
            'trace_id' => $traceId ?? $requestId,
            'endpoint' => $endpoint,
            'filters' => [
                'budget' => $request->query('budget', $request->input('budget_amount')),
                'search' => $request->query('search', $request->input('query')),
                'city' => $request->query('city', $request->input('city')),
                'category' => $request->query('category', $request->input('category')),
                'limit' => $request->query('limit', $request->input('limit')),
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

        GuideRequestTelemetry::recordGuideResponse(
            request: $request,
            requestId: $requestId,
            statusCode: 200,
            meta: $meta,
            latencyMs: $latencyMs,
            candidateCount: $candidateCount,
            tenantCount: $tenantCount,
            quotaStatus: $quotaStatus,
            endpoint: $endpoint,
            traceId: $traceId ?? $requestId,
        );

        $this->logGuideUsage($request, (string) ($meta['ranking_mode'] ?? 'sql'));

        return response()
            ->json($payload)
            ->header('x-request-id', $requestId)
            ->header('x-trace-id', $traceId ?? $requestId);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{status:string,probable_cause:string,recommended_action:string,trace_id:string}
     */
    private function guideDiagnostic(array $meta, int $candidateCount, string $traceId): array
    {
        $degradedReason = (string) ($meta['degraded_reason'] ?? '');

        if ($candidateCount <= 0) {
            return [
                'status' => 'Sin recomendaciones visibles',
                'probable_cause' => $degradedReason !== '' ? $degradedReason : 'No hay negocios publicados que coincidan con presupuesto, ciudad y categoria.',
                'recommended_action' => 'Prueba ampliar presupuesto, quitar filtros o explorar otra categoria.',
                'trace_id' => $traceId,
            ];
        }

        if ((bool) ($meta['degraded_to_search'] ?? false)) {
            return [
                'status' => 'Busqueda segura',
                'probable_cause' => $degradedReason !== '' ? $degradedReason : 'La guia uso resultados publicados como fallback.',
                'recommended_action' => 'Revisa las opciones y ajusta presupuesto/filtros si necesitas mas precision.',
                'trace_id' => $traceId,
            ];
        }

        if ($degradedReason !== '') {
            return [
                'status' => 'Respuesta degradada',
                'probable_cause' => $degradedReason,
                'recommended_action' => 'Puedes usar estos resultados; si se repite, comparte la referencia con soporte.',
                'trace_id' => $traceId,
            ];
        }

        return [
            'status' => 'Recomendaciones listas',
            'probable_cause' => 'La guia encontro negocios publicados dentro de tus filtros.',
            'recommended_action' => 'Abre una recomendacion o ajusta el presupuesto para comparar alternativas.',
            'trace_id' => $traceId,
        ];
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

        if (str_starts_with($reason, 'qdrant_')) {
            return 'qdrant_unavailable';
        }

        return match ($reason) {
            'sql_recommendation_query_failed' => 'sql_recommendation_query_failed',
            'openai_auth_failed' => 'openai_auth_failed',
            'openai_timeout' => 'openai_timeout',
            'qdrant_unavailable' => 'qdrant_unavailable',
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

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     */
    private function chatAnswer(array $recommendations, ?float $budgetAmount, string $currency): string
    {
        if ($recommendations === []) {
            return 'No encontramos opciones publicadas para los filtros actuales.';
        }

        $head = array_slice($recommendations, 0, 3);
        $summaries = [];

        foreach ($head as $entry) {
            $tenantName = (string) data_get($entry, 'tenant.name', data_get($entry, 'name', 'Negocio'));
            $minPrice = (float) data_get($entry, 'price_range.min', 0);

            if ($minPrice > 0) {
                $summaries[] = sprintf('%s desde %.2f %s', $tenantName, $minPrice, strtoupper($currency));
            } else {
                $summaries[] = $tenantName;
            }
        }

        $budgetLabel = is_numeric($budgetAmount)
            ? sprintf('Con %.2f %s, ', (float) $budgetAmount, strtoupper($currency))
            : '';

        return $budgetLabel.'te recomiendo: '.implode('; ', $summaries).'.';
    }

    /**
     * @return array{summary: string, factors: list<string>, degraded_reason: string|null, reasoning_basis: string}
     */
    private function rankingExplanation(string $rankingMode, string $reasoningBasis, ?string $degradedReason): array
    {
        if ($rankingMode === 'fallback') {
            return [
                'summary' => 'Se aplico busqueda publica como degradacion segura para mantener resultados disponibles.',
                'factors' => ['published_only', 'city_filter', 'category_filter', 'search_match'],
                'degraded_reason' => $degradedReason,
                'reasoning_basis' => $reasoningBasis,
            ];
        }

        if ($rankingMode === 'hybrid') {
            $factors = ['published_only', 'budget_filter', 'city_filter', 'category_filter', 'embedding_rerank'];

            if (str_contains($reasoningBasis, 'qdrant')) {
                $factors[] = 'qdrant_semantic_filter';
            }

            return [
                'summary' => str_contains($reasoningBasis, 'qdrant')
                    ? 'Ranking hibrido: recuperacion semantica en Qdrant + filtros SQL publicados/presupuesto.'
                    : 'Ranking hibrido: filtro SQL por presupuesto + reordenamiento semantico con embeddings.',
                'factors' => $factors,
                'degraded_reason' => $degradedReason,
                'reasoning_basis' => $reasoningBasis,
            ];
        }

        return [
            'summary' => 'Ranking SQL deterministico por disponibilidad y ajuste al presupuesto.',
            'factors' => ['published_only', 'budget_filter', 'city_filter', 'category_filter'],
            'degraded_reason' => $degradedReason,
            'reasoning_basis' => $reasoningBasis,
        ];
    }
}
