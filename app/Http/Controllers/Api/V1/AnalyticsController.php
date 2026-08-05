<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Interactions\DTOs\InteractionData;
use App\Domain\Analytics\Enums\InteractionType;
use App\Domain\TenantGovernance\Services\TenantResolver;
use App\Http\Controllers\Controller;
use App\Infrastructure\Interactions\Actions\RecordInteractionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;

final class AnalyticsController extends Controller
{
    public function __construct(
        private TenantResolver $tenantResolver,
        private LoggerInterface $log,
        private RecordInteractionAction $recordInteractionAction,
    ) {}

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
            'tenant_id' => ['required', 'uuid'],
            'type' => ['required', 'string', Rule::in(InteractionType::publicSignalValues())],
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $request->input('tenant_id'))
                ),
            ],
            'metadata' => ['nullable', 'array'],
            'url' => ['nullable', 'string', 'max:1024'],
            'referrer' => ['nullable', 'string', 'max:1024'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'utm_content' => ['nullable', 'string', 'max:150'],
        ]);

        $tenantId = (string) $validated['tenant_id'];
        if (! $this->tenantResolver->isPublic($tenantId)) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found or not available',
            ], 404);
        }

        $metadata = $validated['metadata'] ?? [];
        if (isset($validated['product_id'])) {
            $metadata['product_id'] = (int) $validated['product_id'];
        }

        $this->recordInteractionAction->execute(new InteractionData(
            tenantId: $tenantId,
            type: (string) $validated['type'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            sessionId: $this->resolveVisitorId($request),
            productId: isset($validated['product_id']) ? (string) $validated['product_id'] : null,
            referrer: $this->validatedString($validated, 'referrer') ?? $request->headers->get('referer'),
            url: $this->validatedString($validated, 'url'),
            utmSource: $this->validatedString($validated, 'utm_source'),
            utmMedium: $this->validatedString($validated, 'utm_medium'),
            utmCampaign: $this->validatedString($validated, 'utm_campaign'),
            utmTerm: $this->validatedString($validated, 'utm_term'),
            utmContent: $this->validatedString($validated, 'utm_content'),
            metadata: $metadata,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Event accepted for processing',
        ], 202);
    }

    #[OA\Post(
        path: '/api/analytics/conversion',
        summary: 'Deprecated public conversion beacon (server outcomes only)',
        tags: ['Analytics'],
        requestBody: new OA\RequestBody(required: true, description: 'Conversion payload'),
        responses: [
            new OA\Response(response: 410, description: 'Gone: commercial outcomes are server-authoritative'),
        ]
    )]
    public function conversion(Request $request): JsonResponse
    {
        $this->log->notice('Deprecated public conversion beacon rejected', [
            'route' => 'analytics/conversion',
        ]);

        return response()->json([
            'status' => 'gone',
            'reason' => 'server_authoritative_outcomes_only',
            'message' => 'Paid, purchase and revenue outcomes are derived from server records.',
        ], 410);
    }

    private function resolveVisitorId(Request $request): string
    {
        if ($request->hasSession()) {
            return (string) $request->session()->getId();
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validatedString(array $validated, string $key): ?string
    {
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
