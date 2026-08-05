<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Conversation\ConversationRuntime;
use App\Application\Ai\Conversation\PublicConversationInput;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\DataDecision;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Infrastructure\AI\Conversation\EncryptedPublicConversationSubjectResolver;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\LegacySensitivePayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DirectoryGuideChatController extends Controller
{
    private const DOVRA_SOURCE = 'directory_guide_chat';

    public function __construct(
        private readonly ConversationRuntime $runtime,
        private readonly EncryptedPublicConversationSubjectResolver $subjects,
        ?DovraDataIngressGuard $dovraIngress = null,
    ) {
        $this->dovraIngress = $dovraIngress ?? new DovraDataIngressGuard(
            new DataClassificationPolicy(
                new LegacySensitivePayloadRedactor(new SensitiveDataScrubber),
            ),
            new DovraIntegrationSeamRegistry,
        );
    }

    private readonly DovraDataIngressGuard $dovraIngress;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        Validator::make(
            ['idempotency_key' => $idempotencyKey],
            ['idempotency_key' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/']],
        )->validate();

        $effectiveIdempotencyKey = is_string($idempotencyKey)
            ? $idempotencyKey
            : (string) Str::uuid();
        $actorIdentifier = $request->user()?->getAuthIdentifier();
        $actorId = $actorIdentifier === null ? null : (string) $actorIdentifier;

        $decision = $this->dovraIngress->inspect(new DataEnvelope(
            sourceSystem: self::DOVRA_SOURCE,
            payload: [
                'message' => (string) $validated['message'],
                'budget' => array_key_exists('budget', $validated) ? $validated['budget'] : null,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : null,
                'category' => array_key_exists('category', $validated) ? $validated['category'] : null,
                'conversation_id' => array_key_exists('conversation_id', $validated) ? $validated['conversation_id'] : null,
                'idempotency_key' => $effectiveIdempotencyKey,
            ],
            tenantId: null,
            actorId: $actorId,
            channel: 'public_ai_web',
            context: [],
        ));

        $failure = $this->ingressFailureResponse($decision);
        if ($failure !== null) {
            return $failure;
        }

        $redactedPayload = $decision->redactedPayload;
        if (! $this->hasValidRedactedPayload($redactedPayload)) {
            return response()->json([
                'message' => 'AI input validation is not available.',
                'code' => 'ai_ingress_unavailable',
            ], 503);
        }

        $message = $redactedPayload['message'];
        $explicitBudget = $redactedPayload['budget'];
        $budget = $explicitBudget === null
            ? $this->extractBudget($message)
            : (float) $explicitBudget;
        $conversationId = $redactedPayload['conversation_id'];
        $city = $redactedPayload['city'];
        $category = $redactedPayload['category'];
        $effectiveIdempotencyKey = (string) $redactedPayload['idempotency_key'];

        $subject = null;
        if ($actorId === null) {
            $subject = $this->subjects->resolve(
                $request->cookie(EncryptedPublicConversationSubjectResolver::COOKIE_NAME),
            );
            $actorId = $subject->actorId;
        }

        $json = null;
        try {
            $response = $this->runtime->public(new PublicConversationInput(
                message: $message,
                idempotencyKey: $effectiveIdempotencyKey,
                channel: 'web',
                conversationId: $conversationId === null ? null : (string) $conversationId,
                budget: $budget,
                city: $city === null ? null : (string) $city,
                category: $category === null ? null : (string) $category,
                rateLimitIdentity: $request->ip() ?? 'unknown',
                actorId: $actorId,
            ));
        } catch (AiConversationConflictException) {
            $json = response()->json([
                'message' => 'Idempotency key was already used for a different request.',
                'code' => 'ai_request_conflict',
            ], 409);
        } catch (AiExecutionDeniedException) {
            $json = response()->json([
                'message' => 'AI conversation is not available.',
                'code' => 'ai_configuration_unavailable',
            ], 503);
        }

        $json ??= response()->json($response->toArray());

        if ($subject?->replacementCookie !== null) {
            $json->withCookie($this->subjects->responseCookie($subject->replacementCookie));
        }

        return $json;
    }

    private function ingressFailureResponse(DataDecision $decision): ?JsonResponse
    {
        if ($decision->allowed) {
            return null;
        }

        if (array_intersect(['entrypoint_not_registered', 'tenant_context_required'], $decision->reasons) !== []) {
            return response()->json([
                'message' => 'AI input validation is not available.',
                'code' => 'ai_ingress_unavailable',
            ], 503);
        }

        return response()->json([
            'message' => 'The submitted AI input is not allowed.',
            'code' => 'ai_input_not_allowed',
        ], 422);
    }

    /**
     * The runtime must never fall back to a pre-inspection value. The guard's
     * redacted payload is the only post-inspection source of request fields.
     *
     * @param  array<string, mixed>  $payload
     */
    private function hasValidRedactedPayload(array $payload): bool
    {
        foreach (['message', 'budget', 'city', 'category', 'conversation_id', 'idempotency_key'] as $key) {
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
            || mb_strlen($idempotencyKey) > 160
            || preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1) {
            return false;
        }

        $budget = $payload['budget'];
        if ($budget !== null
            && (! is_int($budget) && ! is_float($budget) && ! is_string($budget)
                || ! is_numeric($budget)
                || ! is_finite((float) $budget)
                || (float) $budget < 0
                || (float) $budget > 10_000_000)) {
            return false;
        }

        foreach (['city', 'category'] as $key) {
            $value = $payload[$key];
            if ($value !== null
                && (! is_string($value)
                    || mb_strlen($value) > 100
                    || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1)) {
                return false;
            }
        }

        $conversationId = $payload['conversation_id'];

        return $conversationId === null
            || (is_string($conversationId) && Str::isUuid($conversationId));
    }

    private function extractBudget(string $message): ?float
    {
        if (! preg_match('/(?:l\.?|lempiras?|hnl)?\s*(\d+(?:[.,]\d{1,2})?)/i', $message, $matches)) {
            return null;
        }

        $budget = (float) str_replace(',', '.', $matches[1]);

        if (! is_finite($budget) || $budget < 0 || $budget > 10_000_000) {
            throw ValidationException::withMessages([
                'message' => 'El presupuesto indicado debe estar entre 0 y 10,000,000.',
            ]);
        }

        return $budget;
    }
}
