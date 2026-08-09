<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

final class AiConversationController extends Controller
{
    public function __construct(
        private readonly AiContentCipher $cipher,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $this->tenantId();
        $userId = $this->userId($request);
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 25);
        $supportAccess = $this->hasSupportAccess($tenantId, $userId);

        $query = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId);

        if (! $supportAccess) {
            $query->where(function ($builder) use ($tenantId, $userId): void {
                $builder
                    ->where('actor_id', $userId)
                    ->orWhereExists(function ($participantQuery) use ($tenantId, $userId): void {
                        $participantQuery
                            ->selectRaw('1')
                            ->from('ai_conversation_participants')
                            ->whereColumn('ai_conversation_participants.conversation_id', 'ai_conversations.id')
                            ->where('ai_conversation_participants.tenant_id', $tenantId)
                            ->where('ai_conversation_participants.participant_id', $userId)
                            ->whereNull('ai_conversation_participants.left_at');
                    });
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (object $conversation): array => $this->presentConversationSummary($tenantId, $conversation))->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        $tenantId = $this->tenantId();
        $row = $this->authorizedConversation($request, $conversation);

        $messages = DB::table('ai_messages')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversation)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                ...$this->presentConversationSummary($tenantId, $row),
                'messages' => $messages->map(fn (object $message): array => $this->presentMessage($tenantId, $message))->all(),
            ],
        ]);
    }

    public function messages(Request $request, string $conversation): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'after_sequence' => ['sometimes', 'integer', 'min:0'],
            'after_id' => ['nullable', 'uuid'],
        ]);

        $tenantId = $this->tenantId();
        $this->authorizedConversation($request, $conversation);

        $perPage = (int) ($data['per_page'] ?? 25);
        $query = DB::table('ai_messages')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversation);

        if (isset($data['after_sequence'])) {
            $afterSequence = (int) $data['after_sequence'];
            $afterId = isset($data['after_id']) ? (string) $data['after_id'] : null;

            $query->where(function ($builder) use ($afterSequence, $afterId): void {
                $builder->where('sequence', '>', $afterSequence);

                if ($afterId !== null) {
                    $builder->orWhere(function ($cursorQuery) use ($afterSequence, $afterId): void {
                        $cursorQuery
                            ->where('sequence', $afterSequence)
                            ->where('id', '>', $afterId);
                    });
                }
            });
        }

        $rows = $query
            ->orderBy('sequence')
            ->orderBy('id')
            ->limit($perPage + 1)
            ->get();

        $page = $rows->take($perPage)->values();
        $next = $rows->count() > $perPage ? $page->last() : null;

        return response()->json([
            'data' => $page->map(fn (object $message): array => $this->presentMessage($tenantId, $message))->all(),
            'meta' => [
                'per_page' => $perPage,
                'next_cursor' => $next === null
                    ? null
                    : ['sequence' => (int) $next->sequence, 'id' => (string) $next->id],
            ],
        ]);
    }

    public function handoff(Request $request, string $conversation): JsonResponse
    {
        $tenantId = $this->tenantId();
        $this->authorizedConversation($request, $conversation);

        $handoff = $this->latestHandoff($tenantId, $conversation);
        abort_if($handoff === null, 404);

        return response()->json([
            'data' => $this->presentHandoff($tenantId, $handoff),
        ]);
    }

    private function authorizedConversation(Request $request, string $conversationId): object
    {
        $tenantId = $this->tenantId();
        $userId = $this->userId($request);

        $conversation = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->first();

        abort_if($conversation === null, 404);

        if ($this->hasSupportAccess($tenantId, $userId)
            || (is_string($conversation->actor_id) && hash_equals((string) $conversation->actor_id, $userId))
            || DB::table('ai_conversation_participants')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->where('participant_id', $userId)
                ->whereNull('left_at')
                ->exists()) {
            return $conversation;
        }

        abort(403);
    }

    /** @return array<string, mixed> */
    private function presentConversationSummary(string $tenantId, object $conversation): array
    {
        $handoff = $this->latestHandoff($tenantId, (string) $conversation->id);

        return [
            'id' => (string) $conversation->id,
            'visibility' => (string) $conversation->visibility,
            'origin_type' => (string) $conversation->origin_type,
            'status' => (string) $conversation->status,
            'path' => 'canonical',
            'retention_category' => (string) $conversation->retention_category,
            'last_message_sequence' => (int) $conversation->last_message_sequence,
            'latest_handoff' => $handoff === null ? null : $this->presentHandoff($tenantId, $handoff),
            'created_at' => $conversation->created_at,
            'updated_at' => $conversation->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    private function presentMessage(string $tenantId, object $message): array
    {
        return [
            'id' => (string) $message->id,
            'sequence' => (int) $message->sequence,
            'direction' => (string) $message->direction,
            'role' => (string) $message->role,
            'status' => (string) $message->status,
            'content_type' => (string) $message->content_type,
            'content' => $this->decryptContent($tenantId, $message),
            'accepted_at' => $message->accepted_at,
            'completed_at' => $message->completed_at,
            'failed_at' => $message->failed_at,
        ];
    }

    /** @return array<string, mixed> */
    private function presentHandoff(string $tenantId, object $handoff): array
    {
        return [
            'id' => (string) $handoff->id,
            'status' => (string) $handoff->status,
            'priority' => $handoff->priority === null ? null : (string) $handoff->priority,
            'queue_key' => $handoff->queue_key === null ? null : (string) $handoff->queue_key,
            'context_summary' => $this->decryptOptional(
                tenantId: $tenantId,
                recordId: (string) $handoff->id,
                contentClass: 'handoff_context',
                ciphertext: $handoff->context_ciphertext,
                hash: $handoff->context_hash,
                keyVersion: $handoff->encryption_key_version,
            ),
            'requested_at' => $handoff->requested_at,
            'queued_at' => $handoff->queued_at,
            'assigned_at' => $handoff->assigned_at,
            'accepted_at' => $handoff->accepted_at,
            'resolved_at' => $handoff->resolved_at,
        ];
    }

    private function latestHandoff(string $tenantId, string $conversationId): ?object
    {
        return DB::table('ai_handoffs')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->orderByDesc(DB::raw('COALESCE(resolved_at, assigned_at, queued_at, requested_at, created_at)'))
            ->orderByDesc('id')
            ->first();
    }

    private function decryptContent(string $tenantId, object $message): mixed
    {
        if ($message->content_ciphertext === null
            || $message->content_hash === null
            || $message->encryption_key_version === null) {
            return null;
        }

        $plaintext = $this->cipher->decrypt(
            $tenantId,
            (string) $message->id,
            'conversation_message',
            new EncryptedAiContent(
                ciphertext: (string) $message->content_ciphertext,
                contentHash: (string) $message->content_hash,
                keyVersion: (string) $message->encryption_key_version,
            ),
        );

        if ((string) $message->content_type !== 'application/json') {
            return $plaintext;
        }

        try {
            return json_decode($plaintext, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private function decryptOptional(
        string $tenantId,
        string $recordId,
        string $contentClass,
        mixed $ciphertext,
        mixed $hash,
        mixed $keyVersion,
    ): ?string {
        if (! is_string($ciphertext) || ! is_string($hash) || ! is_string($keyVersion)) {
            return null;
        }

        return $this->cipher->decrypt(
            $tenantId,
            $recordId,
            $contentClass,
            new EncryptedAiContent(
                ciphertext: $ciphertext,
                contentHash: $hash,
                keyVersion: $keyVersion,
            ),
        );
    }

    private function hasSupportAccess(string $tenantId, string $userId): bool
    {
        $tenantOwnerId = DB::table('tenants')->where('id', $tenantId)->value('user_id');
        if (is_string($tenantOwnerId) && hash_equals($tenantOwnerId, $userId)) {
            return true;
        }

        $role = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->whereNull('suspended_at')
            ->value('role');

        return in_array($role, [
            Role::OWNER->value,
            Role::ADMIN->value,
            Role::OPERATOR->value,
        ], true);
    }

    private function tenantId(): string
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            abort(403);
        }

        return $this->tenantContext->id();
    }

    private function userId(Request $request): string
    {
        $user = $request->user();
        abort_unless(is_object($user), 401);

        return (string) $user->getAuthIdentifier();
    }
}
