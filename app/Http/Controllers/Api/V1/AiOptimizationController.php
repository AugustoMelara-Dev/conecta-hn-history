<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Contracts\TenantContext;
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
        private readonly TenantContext $tenantContext
    ) {}

    public function optimizeProfile(OptimizeProfileRequest $request): JsonResponse
    {
        $tenantUuid = $this->tenantContext->getTenantUuid();

        GenerateBusinessProfileJob::dispatch(
            businessName: $request->validated('business_name'),
            description: $request->validated('description'),
            userId: 0,
            tenantId: $tenantUuid,
            targetAudience: $request->validated('target_audience'),
            tone: $request->validated('tone'),
            keywords: $request->validated('keywords') ?? []
        );

        return response()->json([
            'status' => 'processing',
            'message' => 'Profile optimization has been queued.',
        ], 202);
    }
}
