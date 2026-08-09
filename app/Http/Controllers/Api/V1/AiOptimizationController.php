<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\PrivateAiAccessGate;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\DataDecision;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\DovraTenantBusinessContextResolver;
use App\Infrastructure\Privacy\Dovra\LegacySensitivePayloadRedactor;
use App\Jobs\GenerateBusinessProfileJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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
    private const DOVRA_SOURCE = 'private_ai_prompt';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PrivateAiAccessGate $privateAiAccess,
        ?DovraDataIngressGuard $dovraIngress = null,
        ?DovraTenantBusinessContextResolver $businessContext = null,
    ) {
        $this->businessContext = $businessContext ?? new DovraTenantBusinessContextResolver;
        $this->dovraIngress = $dovraIngress ?? new DovraDataIngressGuard(
            new DataClassificationPolicy(
                new LegacySensitivePayloadRedactor(new SensitiveDataScrubber),
            ),
            new DovraIntegrationSeamRegistry,
        );
    }

    private readonly DovraDataIngressGuard $dovraIngress;

    private readonly DovraTenantBusinessContextResolver $businessContext;

    public function optimizeProfile(OptimizeProfileRequest $request): JsonResponse
    {
        try {
            $tenantId = trim((string) $this->tenantContext->id());
        } catch (\Throwable) {
            return $this->ingressUnavailableResponse();
        }

        if ($tenantId === '') {
            return $this->ingressUnavailableResponse();
        }

        $user = $request->user();

        if (! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        try {
            $policyBusinessType = $this->businessContext->resolvePolicyBusinessType($tenantId);
        } catch (\Throwable) {
            return $this->ingressUnavailableResponse();
        }
        if (! is_string($policyBusinessType) || trim($policyBusinessType) === '') {
            return $this->ingressUnavailableResponse();
        }

        $actorIdentifier = $user?->getAuthIdentifier();
        $actorId = $actorIdentifier === null ? '' : trim((string) $actorIdentifier);
        if ($actorId === '') {
            return $this->ingressUnavailableResponse();
        }

        $validated = $request->validated();
        try {
            $decision = $this->dovraIngress->inspect(new DataEnvelope(
                sourceSystem: self::DOVRA_SOURCE,
                payload: $validated,
                tenantId: $tenantId,
                actorId: $actorId,
                channel: 'private_ai_web',
                context: ['business_type' => $policyBusinessType],
            ));
        } catch (\Throwable) {
            return $this->ingressUnavailableResponse();
        }

        $failure = $this->ingressFailureResponse($decision);
        if ($failure !== null) {
            return $failure;
        }

        $redactedPayload = $decision->redactedPayload;
        if (! $this->hasValidRedactedPayload($redactedPayload)) {
            return $this->ingressUnavailableResponse();
        }

        $correlationId = (string) Str::uuid();
        GenerateBusinessProfileJob::dispatch(
            businessName: $redactedPayload['business_name'],
            description: $redactedPayload['description'],
            userId: $actorId,
            tenantId: $tenantId,
            targetAudience: $redactedPayload['target_audience'],
            tone: $redactedPayload['tone'],
            keywords: $redactedPayload['keywords'],
            correlationId: $correlationId,
        );

        return response()->json([
            'status' => 'processing',
            'message' => 'Profile optimization has been queued.',
            'correlation_id' => $correlationId,
        ], 202);
    }

    private function ingressFailureResponse(DataDecision $decision): ?JsonResponse
    {
        if ($decision->allowed) {
            return null;
        }

        if (array_intersect(['entrypoint_not_registered', 'tenant_context_required'], $decision->reasons) !== []) {
            return $this->ingressUnavailableResponse();
        }

        return response()->json([
            'message' => 'The submitted AI input is not allowed.',
            'code' => 'ai_input_not_allowed',
        ], 422);
    }

    private function ingressUnavailableResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'AI input validation is not available.',
            'code' => 'ai_ingress_unavailable',
        ], 503);
    }

    /** @param array<string, mixed> $payload */
    private function hasValidRedactedPayload(array $payload): bool
    {
        $required = ['business_name', 'description', 'target_audience', 'tone', 'keywords'];
        $keys = array_keys($payload);
        sort($keys);
        sort($required);
        if ($keys !== $required) {
            return false;
        }

        foreach ([
            'business_name' => [1, 255],
            'description' => [10, 1000],
            'target_audience' => [1, 255],
        ] as $key => [$minimum, $maximum]) {
            if (! is_string($payload[$key])
                || trim($payload[$key]) === ''
                || mb_strlen($payload[$key]) < $minimum
                || mb_strlen($payload[$key]) > $maximum) {
                return false;
            }
        }

        if (! is_string($payload['tone'])
            || ! in_array($payload['tone'], ['formal', 'casual', 'persuasive', 'witty', 'professional'], true)) {
            return false;
        }

        if (! is_array($payload['keywords'])
            || ! array_is_list($payload['keywords'])
            || $payload['keywords'] === []
            || count($payload['keywords']) > 10) {
            return false;
        }
        foreach ($payload['keywords'] as $keyword) {
            if (! is_string($keyword) || trim($keyword) === '' || mb_strlen($keyword) > 50) {
                return false;
            }
        }

        return true;
    }
}
