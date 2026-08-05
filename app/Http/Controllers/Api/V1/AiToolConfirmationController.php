<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\PrivateAiAccessGate;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiToolConfirmationException;
use App\Http\Controllers\Controller;
use App\Infrastructure\AI\Tools\AiToolConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AiToolConfirmationController extends Controller
{
    public function __construct(
        private readonly PrivateAiAccessGate $privateAiAccess,
        private readonly AiToolConfirmationService $confirmations,
        private readonly TenantContext $tenantContext,
    ) {}

    public function prepare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool' => ['required', 'string', 'max:100'],
            'payload' => ['required', 'array'],
        ]);
        $idempotencyKey = $request->header('Idempotency-Key');
        Validator::make(
            ['idempotency_key' => $idempotencyKey],
            ['idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/']],
        )->validate();

        $user = $request->user();
        $tenantId = (string) $this->tenantContext->id();
        if (! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json(['message' => 'Private AI is not enabled for this tenant.'], 403);
        }

        try {
            $prepared = $this->confirmations->prepare(
                $tenantId,
                $user,
                (string) $validated['tool'],
                $validated['payload'],
                (string) $idempotencyKey,
            );
        } catch (AiToolConfirmationException $exception) {
            return $this->error($exception);
        }

        $confirmation = $prepared['confirmation'];

        return response()->json([
            'requires_confirmation' => true,
            'replayed' => $prepared['replayed'],
            'confirmation' => $this->confirmations->responsePayload(
                $confirmation,
                $prepared['token'],
                includePreview: true,
            ),
        ], $prepared['replayed'] ? 200 : 201);
    }

    public function confirm(Request $request, string $confirmation): JsonResponse
    {
        $validated = $request->validate([
            'confirmation_token' => ['required', 'string', 'size:64'],
        ]);
        $user = $request->user();
        $tenantId = (string) $this->tenantContext->id();
        if (! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json(['message' => 'Private AI is not enabled for this tenant.'], 403);
        }

        try {
            $confirmed = $this->confirmations->confirm(
                $tenantId,
                $user,
                $confirmation,
                (string) $validated['confirmation_token'],
            );
        } catch (AiToolConfirmationException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'replayed' => $confirmed['replayed'],
            'confirmation' => $this->confirmations->responsePayload($confirmed['confirmation']),
            'result' => $confirmed['result'],
        ]);
    }

    public function cancel(Request $request, string $confirmation): JsonResponse
    {
        $validated = $request->validate([
            'confirmation_token' => ['required', 'string', 'size:64'],
        ]);
        $user = $request->user();
        $tenantId = (string) $this->tenantContext->id();
        if (! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json(['message' => 'Private AI is not enabled for this tenant.'], 403);
        }

        try {
            $cancelled = $this->confirmations->cancel(
                $tenantId,
                $user,
                $confirmation,
                (string) $validated['confirmation_token'],
            );
        } catch (AiToolConfirmationException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'confirmation' => $this->confirmations->responsePayload($cancelled),
        ]);
    }

    private function error(AiToolConfirmationException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->reason,
        ], $exception->status);
    }
}
