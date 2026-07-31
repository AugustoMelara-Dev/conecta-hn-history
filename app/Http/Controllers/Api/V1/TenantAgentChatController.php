<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Conversation\ConversationRuntime;
use App\Application\Ai\Conversation\PrivateConversationInput;
use App\Application\Ai\PrivateAiAccessGate;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class TenantAgentChatController extends Controller
{
    public function __construct(
        private readonly PrivateAiAccessGate $privateAiAccess,
        private readonly ConversationRuntime $runtime,
        private readonly TenantContext $tenantContext,
    ) {}

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

        $user = $request->user();
        $tenantId = (string) $this->tenantContext->id();

        if (! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        try {
            $answer = $this->runtime->private(new PrivateConversationInput(
                tenantId: $tenantId,
                actorId: (string) $user->getAuthIdentifier(),
                message: (string) $validated['message'],
                idempotencyKey: is_string($idempotencyKey) ? $idempotencyKey : (string) Str::uuid(),
                channel: 'web',
                conversationId: isset($validated['conversation_id'])
                    ? (string) $validated['conversation_id']
                    : null,
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
}
