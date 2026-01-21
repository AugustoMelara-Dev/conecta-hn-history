<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Analytics\Contracts\AnalyticsReader;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AnalyticsController - Tenant Analytics API
 *
 * REFACTORED:
 * - Uses $request->user()->tenant (middleware-guaranteed) instead of tenants()->first()
 * - Delegates all queries to AnalyticsReader
 * - No direct Eloquent in controller
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsReader $analyticsReader
    ) {}

    /**
     * Get analytics data for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        // SECURE: Tenant resolved via middleware, not manual query
        $tenant = $request->user()?->tenant;

        if (! $tenant) {
            return response()->json(['error' => 'No tenant found'], 404);
        }

        $days = (int) $request->input('days', 30);

        // Validate days range
        $days = max(1, min($days, 365));

        // DELEGATED: All query logic in AnalyticsReader
        $result = $this->analyticsReader->getFilledAnalytics($tenant->id, $days);

        return response()->json($result);
    }
}
