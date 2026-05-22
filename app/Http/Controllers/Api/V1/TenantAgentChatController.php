<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\PrivateAiAccessGate;
use App\Http\Controllers\Controller;
use App\Infrastructure\AI\EloquentTenantAgentReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantAgentChatController extends Controller
{
    public function __construct(
        private readonly PrivateAiAccessGate $privateAiAccess,
        private readonly EloquentTenantAgentReader $reader,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'tenant_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $tenantId = (string) ($request->header('X-Tenant-ID') ?? $validated['tenant_id'] ?? $user?->tenant_id ?? '');

        if ($tenantId === '' || ! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        return response()->json($this->reader->answer($tenantId, (string) $validated['message']));
    }
}
