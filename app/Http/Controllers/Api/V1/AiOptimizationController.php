<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\PrivateAiAccessGate;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Jobs\GenerateBusinessProfileJob;
use Illuminate\Http\JsonResponse;

/**
 * AiOptimizationController - Async AI Profile Generation
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Security: No Auth::user() or Session access.
 * - Context: Resolved via TenantContext.
 * - Validation: Delegated to OptimizeProfileRequest.
 * - Performance: Dispatches to queue, never blocks HTTP worker.
 */
final class AiOptimizationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PrivateAiAccessGate $privateAiAccess,
    ) {}

    public function optimizeProfile(OptimizeProfileRequest $request): JsonResponse
    {
        $tenantId = (string) $this->tenantContext->id();

        if (! $this->privateAiAccess->allows($request->user(), $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        GenerateBusinessProfileJob::dispatch(
            businessName: $request->validated('business_name'),
            description: $request->validated('description'),
            userId: (string) $request->user()->getAuthIdentifier(),
            tenantId: $tenantId,
            targetAudience: $request->validated('target_audience'),
            tone: $request->validated('tone'),
            keywords: $request->validated('keywords') ?? [],
        );

        return response()->json([
            'status' => 'processing',
            'message' => 'Profile optimization has been queued.',
        ], 202);
    }
}
