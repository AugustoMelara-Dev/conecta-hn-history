<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Directory\Contracts\DirectoryGuideReader;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

            return response()->json([
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
            ]);
        }

        try {
            $recommendations = $this->reader->recommendByBudget($budget, $city, $category, $search, $limit);

            if ($recommendations === []) {
                $fallback = $this->reader->searchPublishedTenants($search, $city, $category, $limit);

                return response()->json([
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
                ]);
            }

            $rankingMode = 'sql';
            $reasoningBasis = 'sql_budget_filter';

            if ($hybridRerankEnabled && is_string($search) && trim($search) !== '') {
                try {
                    $recommendations = array_slice($recommendations, 0, $maxRerankCandidates);
                    $recommendations = $this->reader->rerankRecommendations($recommendations, $search, $limit);
                    $rankingMode = 'hybrid';
                    $reasoningBasis = 'sql_filter_plus_embedding_rerank';
                } catch (\Throwable) {
                    $fallback = $this->reader->searchPublishedTenants($search, $city, $category, $limit);

                    return response()->json([
                        'data' => $fallback,
                        'meta' => [
                            'budget' => round($budget, 2),
                            'count' => count($fallback),
                            'degraded_to_search' => true,
                            'degraded_reason' => 'embedding_unavailable',
                            'ranking_mode' => 'fallback',
                            'reasoning_basis' => 'directory_search_fallback',
                            'generated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }
        } catch (\Throwable) {
            $fallback = $this->reader->searchPublishedTenants($search, $city, $category, $limit);

            return response()->json([
                'data' => $fallback,
                'meta' => [
                    'budget' => round($budget, 2),
                    'count' => count($fallback),
                    'degraded_to_search' => true,
                    'degraded_reason' => 'guide_runtime_unavailable',
                    'ranking_mode' => 'fallback',
                    'reasoning_basis' => 'directory_search_fallback',
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);
        }

        return response()->json([
            'data' => $recommendations,
            'meta' => [
                'budget' => round($budget, 2),
                'count' => count($recommendations),
                'degraded_to_search' => false,
                'ranking_mode' => $rankingMode,
                'reasoning_basis' => $reasoningBasis,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
