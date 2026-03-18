<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Analytics\Contracts\AnalyticsReader;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
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
        private AnalyticsReader $analyticsReader
    ) {}

    /**
     * Get analytics data for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tenant instanceof Tenant) {
            return response()->json(['error' => 'No tenant found'], 404);
        }

        $tenant = $user->tenant;

        $days = (int) $request->input('days', 30);

        // Validate days range
        $days = max(1, min($days, 365));

        // DELEGATED: All query logic in AnalyticsReader
        $result = $this->analyticsReader->getFilledAnalytics($tenant->id, $days);

        return response()->json($result);
    }
}
