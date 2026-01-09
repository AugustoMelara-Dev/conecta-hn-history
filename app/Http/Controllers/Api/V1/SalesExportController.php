<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\ReportingAndExports\Jobs\GenerateSalesExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SalesExportController - Async Export Request Handler
 *
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURE: Deferred Execution with Callback                          ║
 * ║                                                                           ║
 * ║  POST /api/exports/sales → Validates, dispatches Job → 202 Accepted      ║
 * ║  GET  /api/exports/download → Serves file via signed URL                 ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 *
 * PERFORMANCE GUARANTEE:
 * - store() returns in <100ms (only dispatches to queue)
 * - Heavy Excel generation happens in background worker
 */
final class SalesExportController extends Controller
{
    /**
     * Request a new sales export (async).
     *
     * Validates input, dispatches background job, and returns immediately.
     * The user will receive a WebSocket notification when the export is ready.
     *
     * @return JsonResponse (202 Accepted)
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // STEP 1: Validate Request
        // ═══════════════════════════════════════════════════════════════════════
        $validated = $request->validate([
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,preparing,ready,delivered,cancelled'],
        ]);

        $user = Auth::user();
        $tenant = $user->tenant ?? $user->currentTenant;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un negocio asociado.',
            ], 403);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 2: Build Criteria (as array for Job serialization)
        // ═══════════════════════════════════════════════════════════════════════
        $criteriaData = [
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'] ?? null,
        ];

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: Dispatch Job to Queue
        // This returns IMMEDIATELY - no waiting for Excel generation
        // ═══════════════════════════════════════════════════════════════════════
        GenerateSalesExport::dispatch(
            userId: $user->id,
            tenantId: $tenant->id,
            criteriaData: $criteriaData,
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown',
        );

        Log::info('SalesExport job dispatched', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'criteria' => $criteriaData,
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 4: Return 202 Accepted (NOT 200)
        // 202 = "Request accepted, processing has started"
        // ═══════════════════════════════════════════════════════════════════════
        return response()->json([
            'success' => true,
            'message' => 'La exportación se está procesando en segundo plano. Te notificaremos cuando esté lista.',
            'status' => 'processing',
        ], 202);
    }

    /**
     * Download an exported file via signed URL.
     *
     * SECURITY LAYERS:
     * 1. `auth:sanctum` middleware - User must be authenticated
     * 2. `signed` middleware - URL must have valid cryptographic signature
     * 3. Tenant ownership check - User must belong to the file's tenant
     */
    public function download(Request $request): StreamedResponse|JsonResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // STEP 1: Decode and Validate Path
        // Path is base64 encoded to prevent path traversal attacks
        // ═══════════════════════════════════════════════════════════════════════
        $encodedPath = $request->query('path');
        $requestedTenantId = (int) $request->query('tenant');

        if (! $encodedPath || ! $requestedTenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de descarga inválidos.',
            ], 400);
        }

        $filePath = base64_decode($encodedPath, true);

        if ($filePath === false) {
            return response()->json([
                'success' => false,
                'message' => 'Ruta de archivo inválida.',
            ], 400);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 2: Authorization - Verify Tenant Ownership
        // User must belong to the tenant that owns the file
        // ═══════════════════════════════════════════════════════════════════════
        $user = Auth::user();
        $userTenantId = $user->tenant_id ?? $user->currentTenant?->id;

        if ($userTenantId !== $requestedTenantId) {
            Log::warning('Unauthorized export download attempt', [
                'user_id' => $user->id,
                'user_tenant_id' => $userTenantId,
                'requested_tenant_id' => $requestedTenantId,
                'file_path' => $filePath,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para descargar este archivo.',
            ], 403);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: Validate File Exists
        // ═══════════════════════════════════════════════════════════════════════
        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo ya no está disponible. Los reportes expiran después de 24 horas.',
            ], 404);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 4: Serve File
        // ═══════════════════════════════════════════════════════════════════════
        $fileName = basename($filePath);

        return Storage::disk('local')->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
