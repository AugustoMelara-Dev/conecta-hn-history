<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\Enums\InteractionType;
use App\Infrastructure\Jobs\Analytics\RecordInteraction;
use App\Domain\TenantGovernance\Services\TenantResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * AnalyticsController - IDOR-Hardened Conversion Event API
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║  - Zero App\Models imports (DIP compliant)                               ║
 * ║  - Uses Domain Enum for validation instead of Model constants            ║
 * ║  - Uses TenantResolver instead of direct Tenant::where()                 ║
 * ║  - Validates tenant is public before recording events                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private TenantResolver $tenantResolver,
        private LoggerInterface $log
    ) {}

    /**
     * Record a conversion event asynchronously.
     */
    #[OA\Post(
        path: '/api/analytics/track',
        summary: 'Track generic analytics event',
        tags: ['Analytics'],
        requestBody: new OA\RequestBody(required: true, description: 'Tracking payload'),
        responses: [
            new OA\Response(response: 202, description: 'Accepted'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer'],
            // DIP COMPLIANT: Uses Domain Enum instead of Model constant
            'type' => ['required', 'string', Rule::in(InteractionType::values())],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        // SECURE: Validate tenant exists and is public via resolver
        if (! $this->tenantResolver->isPublic($validated['tenant_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found or not available',
            ], 404);
        }

        RecordInteraction::dispatch(
            tenantId: $validated['tenant_id'],
            type: $validated['type'],
            productId: $validated['product_id'] ?? null,
            metadata: $validated['metadata'] ?? [],
        );

        return response()->json([
            'success' => true,
            'message' => 'Event accepted for processing',
        ], 202);
    }

    /**
     * Log a conversion event from Beacon API (purchase).
     *
     * SECURITY: Uses TenantResolver for slug lookup.
     */
    #[OA\Post(
        path: '/api/analytics/conversion',
        summary: 'Track conversion/purchase event',
        tags: ['Analytics'],
        requestBody: new OA\RequestBody(required: true, description: 'Conversion payload'),
        responses: [
            new OA\Response(response: 200, description: 'Status OK'),
        ]
    )]
    public function conversion(Request $request): JsonResponse
    {
        try {
            $data = $request->json()->all();

            $tenantSlug = $data['tenant_slug'] ?? null;
            $transactionId = $data['transaction_id'] ?? null;
            $value = $data['value'] ?? 0;
            $currency = $data['currency'] ?? 'HNL';
            $event = $data['event'] ?? 'purchase';
            $timestamp = $data['timestamp'] ?? now()->toISOString();

            if (! $tenantSlug) {
                return response()->json(['status' => 'skipped', 'reason' => 'no_tenant']);
            }

            // SECURE: Use TenantResolver instead of direct Tenant::where()
            $tenant = $this->tenantResolver->findPublicBySlug($tenantSlug);

            if (! $tenant) {
                return response()->json(['status' => 'skipped', 'reason' => 'tenant_not_found']);
            }

            RecordInteraction::dispatch(
                tenantId: $tenant->id,
                type: InteractionType::CONVERSION->value,
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

            $this->log->info('Conversion tracked', [
                'tenant' => $tenantSlug,
                'transaction_id' => $transactionId,
                'value' => $value,
            ]);

            return response()->json([
                'status' => 'ok',
                'transaction_id' => $transactionId,
            ]);
        } catch (\Throwable $e) {
            $this->log->warning('Analytics conversion error', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error']);
        }
    }
}
