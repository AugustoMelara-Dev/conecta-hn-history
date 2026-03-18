<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Interactions\DTOs\InteractionData;
use App\Domain\Shared\Contracts\TenantReadRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInteractionRequest;
use App\Infrastructure\Interactions\Actions\RecordInteractionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class InteractionController extends Controller
{
    public function __construct(
        private readonly TenantReadRepository $tenantReader
    ) {}

    public function store(StoreInteractionRequest $request, RecordInteractionAction $action): JsonResponse
    {
        try {
            $metadata = $request->input('metadata');
            $dto = new InteractionData(
                tenantId: (string) $request->input('tenant_id'),
                type: (string) $request->input('type'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                sessionId: $request->session()->getId(),
                productId: $request->filled('product_id') ? (string) $request->input('product_id') : null,
                metadata: is_array($metadata) ? $metadata : [],
            );

            if (! $this->tenantReader->exists((int) $dto->tenantId)) {
                return response()->json(['status' => 'accepted'], 202);
            }

            $action->execute($dto);

            return response()->json(['status' => 'success'], 201);
        } catch (\Throwable $e) {
            Log::error('Interaction Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'accepted'], 202);
        }
    }
}
