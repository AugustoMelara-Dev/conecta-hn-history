<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Interactions\DTOs\InteractionData;
use App\Domain\Shared\Contracts\TenantReadRepository;
use App\Http\Controllers\Controller;
use App\Infrastructure\Interactions\Actions\RecordInteractionAction;
use Illuminate\Http\JsonResponse;

/**
 * InteractionController - Strict Hexagonal
 */
final class InteractionController extends Controller
{
    public function __construct(
        private readonly TenantReadRepository $tenantReader
    ) {}

    public function store(\App\Http\Requests\StoreInteractionRequest $request, RecordInteractionAction $action): JsonResponse
    {
        try {
            // ARCHITECTURAL FIX: Request→DTO mapping belongs in the Controller (Infrastructure),
            // not in the Application Layer DTO. This restores the Dependency Rule.
            $dto = new InteractionData(
                tenantId: (string) $request->input('tenant_id'),
                type: (string) $request->input('type'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                sessionId: $request->session()->getId(),
                productId: $request->filled('product_id') ? (string) $request->input('product_id') : null,
                metadata: $request->input('metadata'),
            );

            // 1. Check Existence via Repository (No Model used here)
            if (! $this->tenantReader->exists($dto->tenantId)) {
                return response()->json(['status' => 'accepted'], 202);
            }

            $result = $action->execute($dto);

            if ($result === null) {
                return response()->json(['status' => 'duplicate', 'message' => 'Interaction already recorded'], 200);
            }

            return response()->json(['status' => 'success'], 201);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Interaction Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'accepted'], 202);
        }
    }
}
