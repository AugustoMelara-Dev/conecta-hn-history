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
            'tenant_id' => ['required', 'string'],
            'type' => ['required', 'string', Rule::in(InteractionType::values())],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
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
            $tenantSlug = isset($data['tenant_slug']) && is_string($data['tenant_slug']) ? $data['tenant_slug'] : null;
            $transactionId = isset($data['transaction_id']) && is_string($data['transaction_id']) ? $data['transaction_id'] : null;
            $value = isset($data['value']) ? (float) $data['value'] : 0.0;
            $currency = isset($data['currency']) && is_string($data['currency']) ? $data['currency'] : 'HNL';
            $event = isset($data['event']) && is_string($data['event']) ? $data['event'] : InteractionType::PURCHASE->value;
            $timestamp = isset($data['timestamp']) && is_string($data['timestamp']) ? $data['timestamp'] : now()->toISOString();
            $attribution = $this->attributionFromArray($data);

            if ($tenantSlug === null || $tenantSlug === '') {
                return response()->json(['status' => 'skipped', 'reason' => 'no_tenant']);
            }

            $tenant = $this->tenantResolver->findPublicBySlug($tenantSlug);
            if ($tenant === null) {
                return response()->json(['status' => 'skipped', 'reason' => 'tenant_not_found']);
            }

            $this->recordInteractionAction->execute(new InteractionData(
                tenantId: $tenant->id(),
                type: $event,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                sessionId: $this->resolveVisitorId($request),
                productId: null,
                referrer: $this->stringFromArray($data, 'referrer') ?? $request->headers->get('referer'),
                url: $this->stringFromArray($data, 'url'),
                utmSource: $attribution['utm_source'] ?? null,
                utmMedium: $attribution['utm_medium'] ?? null,
                utmCampaign: $attribution['utm_campaign'] ?? null,
                utmTerm: $attribution['utm_term'] ?? null,
                utmContent: $attribution['utm_content'] ?? null,
                metadata: [
                    'event' => $event,
                    'transaction_id' => $transactionId,
                    'value' => $value,
                    'currency' => $currency,
                    'source' => 'beacon',
                    'original_timestamp' => $timestamp,
                ],
            ));

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

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringFromArray(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function attributionFromArray(array $data): array
    {
        return array_filter([
            'utm_source' => $this->stringFromArray($data, 'utm_source'),
            'utm_medium' => $this->stringFromArray($data, 'utm_medium'),
            'utm_campaign' => $this->stringFromArray($data, 'utm_campaign'),
            'utm_term' => $this->stringFromArray($data, 'utm_term'),
            'utm_content' => $this->stringFromArray($data, 'utm_content'),
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
