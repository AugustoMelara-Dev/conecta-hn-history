<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Conversation\ConversationRuntime;
use App\Application\Ai\Conversation\PublicConversationInput;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DirectoryGuideChatController extends Controller
{
    public function __construct(
        private readonly ConversationRuntime $runtime,
    ) {}

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

        $message = (string) $validated['message'];
        $budget = isset($validated['budget'])
            ? (float) $validated['budget']
            : $this->extractBudget($message);

        try {
            $response = $this->runtime->public(new PublicConversationInput(
                message: $message,
                idempotencyKey: is_string($idempotencyKey) ? $idempotencyKey : (string) Str::uuid(),
                channel: 'web',
                conversationId: isset($validated['conversation_id'])
                    ? (string) $validated['conversation_id']
                    : null,
                budget: $budget,
                city: isset($validated['city']) ? (string) $validated['city'] : null,
                category: isset($validated['category']) ? (string) $validated['category'] : null,
                rateLimitIdentity: $request->ip() ?? 'unknown',
                actorId: $request->user()?->getAuthIdentifier(),
            ));
        } catch (AiConversationConflictException) {
            return response()->json([
                'message' => 'Idempotency key was already used for a different request.',
                'code' => 'ai_request_conflict',
            ], 409);
        } catch (AiExecutionDeniedException) {
            return response()->json([
                'message' => 'AI conversation is not available.',
                'code' => 'ai_configuration_unavailable',
            ], 503);
        }

        return response()->json($response->toArray());
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
