<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Analytics\Jobs\RecordInteraction;
use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * AnalyticsController - Conversion Event API
 *
 * ARCHITECTURE:
 * Receives tracking events from frontend components:
 * - WhatsApp button clicks
 * - Phone call initiations
 * - Cart additions
 * - Purchase conversions (Beacon API)
 *
 * PERFORMANCE:
 * Uses async job dispatch to avoid blocking HTTP responses.
 * The user receives a 202 Accepted immediately while the
 * database write happens in a background worker.
 *
 * ENDPOINTS:
 * - POST /api/analytics/track - Standard event tracking
 * - POST /api/analytics/conversion - Beacon API purchase tracking
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

    /**
     * Log a conversion event from Beacon API (purchase)
     *
     * POST /api/analytics/conversion
     *
     * Beacon API sends JSON with:
     * - event: 'purchase'
     * - transaction_id: string
     * - value: number
     * - currency: 'HNL'
     * - tenant_slug: string
     * - timestamp: ISO8601
     *
     * IMPORTANT: Always returns 200 - Beacon API doesn't retry.
     */
    public function conversion(Request $request): JsonResponse
    {
        try {
            $data = $request->json()->all();

            // Extract fields with fallbacks
            $tenantSlug = $data['tenant_slug'] ?? null;
            $transactionId = $data['transaction_id'] ?? null;
            $value = $data['value'] ?? 0;
            $currency = $data['currency'] ?? 'HNL';
            $event = $data['event'] ?? 'purchase';
            $timestamp = $data['timestamp'] ?? now()->toISOString();

            if (! $tenantSlug) {
                return response()->json(['status' => 'skipped', 'reason' => 'no_tenant']);
            }

            // Find tenant by slug
            $tenant = Tenant::where('slug', $tenantSlug)->first();

            if (! $tenant) {
                return response()->json(['status' => 'skipped', 'reason' => 'tenant_not_found']);
            }

            // Dispatch async job for recording
            RecordInteraction::dispatch(
                tenantId: $tenant->id,
                type: 'conversion',
                productId: null,
                metadata: [
                    'event' => $event,
                    'transaction_id' => $transactionId,
                    'value' => $value,
                    'currency' => $currency,
                    'source' => 'beacon',
                    'original_timestamp' => $timestamp,
                ],
            );

            // Log for monitoring
            Log::channel('daily')->info('Conversion tracked', [
                'tenant' => $tenantSlug,
                'transaction_id' => $transactionId,
                'value' => $value,
            ]);

            return response()->json([
                'status' => 'ok',
                'transaction_id' => $transactionId,
            ]);
        } catch (\Throwable $e) {
            // Always return 200 for beacon - it doesn't retry
            Log::warning('Analytics conversion error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error']);
        }
    }
}
