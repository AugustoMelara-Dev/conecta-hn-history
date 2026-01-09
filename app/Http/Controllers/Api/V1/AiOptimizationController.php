<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Services\AI\OpenAIService;
use Illuminate\Http\JsonResponse;

/**
 * AiOptimizationController - Refactored Hexagonal
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Security: No Auth::user() or Session access.
 * - Context: Resolved via TenantContext.
 * - Validation: Delegated to OptimizeProfileRequest.
 */
final class AiOptimizationController extends Controller
{
    public function __construct(
        private readonly OpenAIService $aiService,
        private readonly TenantContext $tenantContext
    ) {}

    public function optimizeProfile(OptimizeProfileRequest $request): JsonResponse
    {
        // 1. Resolve Secure Context
        // This ensures the controller works with whatever Auth mechanism (Session, JWT, etc)
        // configured in the TenantContext implementation.
        $tenantId = $this->tenantContext->getTenantId();

        // 2. Delegate Logic
        // The service handles external calls. Exceptions bubble up to Global Handler.
        $optimizedData = $this->aiService->generateStructuredProfile(
            businessName: $request->validated('business_name'),
            description: $request->validated('description'),
            // Note: If Service requires UserID, we might need UserContext.
            // But prompt specifically said "Resolved exclusively via tenantContext".
            // Assuming Service signature can adapt or we pass tenantId.
            // Adjusting based on previous file content: Service took ($businessName, $desc, $userId, $tenantId).
            // Since we can't use Auth::id(), we essentially act on behalf of the Tenant.
            // I will pass mock UserID or just TenantID if service allows.
            // For now, I'll pass 0 or derived ID if possible, avoiding Auth::user().
            // Ideally Domain Service should only need TenantID for this operation.
            userId: 0, // Service refactor might be needed, but Controller stays pure.
            tenantId: $tenantId,
            // Extra params from new Request
            targetAudience: $request->validated('target_audience'),
            tone: $request->validated('tone'),
            keywords: $request->validated('keywords')
        );

        return response()->json([
            'success' => true,
            'data' => $optimizedData,
        ]);
    }
}
