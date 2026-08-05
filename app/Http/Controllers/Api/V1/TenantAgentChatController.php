<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Conversation\ConversationRuntime;
use App\Application\Ai\Conversation\PrivateConversationInput;
use App\Application\Ai\PrivateAiAccessGate;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\DataDecision;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\DovraTenantBusinessContextResolver;
use App\Infrastructure\Privacy\Dovra\LegacySensitivePayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class TenantAgentChatController extends Controller
{
    private const DOVRA_SOURCE = 'tenant_agent_chat';

    public function __construct(
        private readonly PrivateAiAccessGate $privateAiAccess,
        private readonly ConversationRuntime $runtime,
        private readonly TenantContext $tenantContext,
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

    private readonly DovraTenantBusinessContextResolver $businessContext;

    private readonly DovraDataIngressGuard $dovraIngress;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        Validator::make(
            ['idempotency_key' => $idempotencyKey],
            ['idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/']],
        )->validate();

        $effectiveIdempotencyKey = is_string($idempotencyKey)
            ? $idempotencyKey
            : (string) Str::uuid();
        $user = $request->user();
        try {
            $tenantId = trim((string) $this->tenantContext->id());
        } catch (\Throwable) {
            return $this->ingressUnavailableResponse();
        }

        if ($tenantId === '') {
            return $this->ingressUnavailableResponse();
        }

        if (! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        $policyBusinessType = $this->businessContext->resolvePolicyBusinessType($tenantId);
        if ($policyBusinessType === null) {
            return $this->ingressUnavailableResponse();
        }

        $actorIdentifier = $user?->getAuthIdentifier();
        $actorId = $actorIdentifier === null ? '' : (string) $actorIdentifier;

        $decision = $this->dovraIngress->inspect(new DataEnvelope(
            sourceSystem: self::DOVRA_SOURCE,
            payload: [
                'message' => (string) $validated['message'],
                'conversation_id' => array_key_exists('conversation_id', $validated) ? $validated['conversation_id'] : null,
                'idempotency_key' => $effectiveIdempotencyKey,
            ],
            tenantId: $tenantId,
            actorId: $actorId,
            channel: 'private_ai_web',
            context: ['business_type' => $policyBusinessType],
        ));

        $failure = $this->ingressFailureResponse($decision);
        if ($failure !== null) {
            return $failure;
        }

        $redactedPayload = $decision->redactedPayload;
        if (! $this->hasValidRedactedPayload($redactedPayload)) {
            return $this->ingressUnavailableResponse();
        }

        $message = $redactedPayload['message'];
        $conversationId = $redactedPayload['conversation_id'];
        $effectiveIdempotencyKey = (string) $redactedPayload['idempotency_key'];

        try {
            $answer = $this->runtime->private(new PrivateConversationInput(
                tenantId: $tenantId,
                actorId: $actorId,
                message: $message,
                idempotencyKey: $effectiveIdempotencyKey,
                channel: 'web',
                conversationId: $conversationId === null ? null : (string) $conversationId,
            ));
        } catch (AiConversationConflictException) {
            return response()->json([
                'message' => 'Idempotency key was already used for a different request.',
                'code' => 'ai_request_conflict',
            ], 409);
        } catch (AiExecutionDeniedException $exception) {
            if ($exception->reason !== 'ai_access_denied') {
                throw $exception;
            }

            return response()->json([
                'message' => 'AI conversation is not available.',
                'code' => 'ai_access_denied',
            ], 403);
        }

        return response()->json($answer->toArray());
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

    /**
     * Request fields are consumed only from the guard's redacted payload.
     * Missing or malformed redacted values are an ingress configuration
     * failure; falling back to the original request would bypass the guard.
     *
     * @param  array<string, mixed>  $payload
     */
    private function hasValidRedactedPayload(array $payload): bool
    {
        foreach (['message', 'conversation_id', 'idempotency_key'] as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        if (! is_string($payload['message'])
            || trim($payload['message']) === ''
            || mb_strlen($payload['message']) < 3
            || mb_strlen($payload['message']) > 500) {
            return false;
        }

        $idempotencyKey = $payload['idempotency_key'];
        if (! is_string($idempotencyKey)
            || trim($idempotencyKey) === ''
            || mb_strlen($idempotencyKey) > 100
            || preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1) {
            return false;
        }

        $conversationId = $payload['conversation_id'];

        return $conversationId === null
            || (is_string($conversationId) && Str::isUuid($conversationId));
    }
}
