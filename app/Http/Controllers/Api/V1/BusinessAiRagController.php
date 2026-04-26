<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\BusinessAi\Contracts\BusinessAiAuthorizerInterface;
use App\Application\BusinessAi\Contracts\BusinessRagServiceInterface;
use App\Http\Controllers\Controller;
use App\Jobs\BusinessAi\ReindexTenantBusinessKnowledgeJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\RequestTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BusinessAiRagController extends Controller
{
    public function __construct(
        private readonly RequestTenantResolver $tenantResolver,
        private readonly BusinessAiAuthorizerInterface $authorizer,
        private readonly BusinessRagServiceInterface $ragService,
    ) {}

    public function reindex(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $user = $this->user($request);

        abort_unless($this->authorizer->authorize($user, $tenant), 403);

        ReindexTenantBusinessKnowledgeJob::dispatch(
            tenantId: (string) $tenant->getKey(),
            requestedBy: (string) $user->getAuthIdentifier(),
        );

        return response()->json([
            'status' => 'queued',
            'scope' => 'tenant_private',
            'tenant_id' => (string) $tenant->getKey(),
            'message' => 'El conocimiento privado se esta reindexando en segundo plano.',
        ], 202);
    }

    public function chat(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $user = $this->user($request);

        abort_unless($this->authorizer->authorize($user, $tenant), 403);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:8'],
        ]);

        $payload = $this->ragService->answer(
            tenant: $tenant,
            query: (string) $validated['query'],
            userId: (string) $user->getAuthIdentifier(),
            limit: (int) ($validated['limit'] ?? 6),
        );

        return response()->json([
            'data' => [
                'answer' => $payload['answer'],
                'sources' => $payload['sources'],
            ],
            'meta' => $payload['meta'],
        ]);
    }

    private function tenant(Request $request): Tenant
    {
        return $this->tenantResolver->resolveExplicit($request);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
