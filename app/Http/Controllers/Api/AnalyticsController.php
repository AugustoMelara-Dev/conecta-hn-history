<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RecordInteraction;
use App\Models\Interaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AnalyticsController - Conversion Event API
 * 
 * Receives tracking events from frontend components:
 * - WhatsApp button clicks
 * - Phone call initiations
 * - Cart additions
 * 
 * PERFORMANCE ARCHITECTURE:
 * Uses async job dispatch to avoid blocking HTTP responses.
 * The user receives a 202 Accepted immediately while the
 * database write happens in a background worker.
 * 
 * SECURITY:
 * - Rate limited to prevent spam (configured in routes)
 * - Validates event types against whitelist
 */
class AnalyticsController extends Controller
{
    /**
     * Record a conversion event asynchronously.
     * 
     * POST /api/analytics/track
     * 
     * PERFORMANCE:
     * Returns immediately (202 Accepted) without waiting for DB write.
     * Job processes in background, reducing TTFB by ~50-200ms.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'type' => ['required', 'string', Rule::in(array_keys(Interaction::TYPES))],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire and forget - don't block HTTP response
        // ═══════════════════════════════════════════════════════════════════
        RecordInteraction::dispatch(
            tenantId: $validated['tenant_id'],
            type: $validated['type'],
            productId: $validated['product_id'] ?? null,
            metadata: $validated['metadata'] ?? [],
        );

        // Return 202 Accepted - event accepted for async processing
        return response()->json([
            'success' => true,
            'message' => 'Event accepted for processing',
        ], 202);
    }
}
