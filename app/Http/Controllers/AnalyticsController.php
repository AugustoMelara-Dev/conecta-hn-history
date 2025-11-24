<?php

namespace App\Http\Controllers;

use App\Models\TenantAnalytic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    /**
     * Get analytics data for the authenticated tenant owner
     *
     * Pilar 8: Serve analytics data for frontend visualization
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get user's tenant
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            return response()->json(['error' => 'No tenant found'], 404);
        }

        // Get date range (default: last 30 days)
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Fetch analytics data
        $analytics = TenantAnalytic::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->date->format('Y-m-d'),
                    'visits' => $record->unique_visits,
                    'total' => $record->total_visits,
                ];
            });

        // Fill in missing dates with zeros
        $filledData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $existing = $analytics->firstWhere('date', $dateStr);

            $filledData[] = [
                'date' => $dateStr,
                'visits' => $existing['visits'] ?? 0,
                'total' => $existing['total'] ?? 0,
            ];

            $currentDate->addDay();
        }

        // Calculate summary stats
        $totalVisits = $analytics->sum('visits');
        $avgVisits = $analytics->avg('visits');
        $peakDay = $analytics->sortByDesc('visits')->first();

        return response()->json([
            'data' => $filledData,
            'summary' => [
                'total_unique_visits' => $totalVisits,
                'average_daily_visits' => round($avgVisits, 1),
                'peak_day' => $peakDay ? [
                    'date' => $peakDay['date'],
                    'visits' => $peakDay['visits'],
                ] : null,
            ],
        ]);
    }
}
