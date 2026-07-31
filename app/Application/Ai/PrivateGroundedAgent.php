<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Conversation\ConversationEngineResult;
use App\Application\Ai\Conversation\ConversationMessageClaim;
use App\Application\Ai\Conversation\PrivateConversationInput;
use App\Application\Ai\DTOs\AiExecutionContext;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\EloquentTenantAgentReader;
use Illuminate\Support\Facades\Log;

final class PrivateGroundedAgent
{
    public function __construct(
        private readonly EloquentTenantAgentReader $reader,
        private readonly AiExecutionContextFactory $contexts,
        private readonly AiGateway $gateway,
    ) {}

    public function answer(
        PrivateConversationInput $input,
        AiRuntimeSelection $selection,
        ConversationMessageClaim $claim,
    ): ConversationEngineResult {
        $fallback = $this->reader->answer($input->tenantId, $input->message);
        $grounding = is_array($fallback['grounding'] ?? null) ? $fallback['grounding'] : [];
        $tool = $fallback['tool'] ?? null;

        $intent = $fallback['data']['intent'] ?? null;
        if ($grounding === [] || ! is_string($tool) || $tool === ''
            || ! in_array($intent, ['catalog', 'availability'], true)) {
            return new ConversationEngineResult(
                payload: $this->presentFallback($fallback),
                evidence: [],
                execution: null,
                fallback: true,
            );
        }

        $context = $this->contexts->create(
            capability: 'private_tenant_agent',
            rateLimitIdentity: 'private-agent:'.$input->tenantId.':'.$input->actorId,
            tenantId: $input->tenantId,
            actorId: $input->actorId,
            authorized: true,
            entitled: true,
            allowedTools: [$tool],
            correlationId: $claim->requestMessage->id,
            conversationId: $claim->conversationId,
            requestMessageId: $claim->requestMessage->id,
        );
        [$prompt, $visibleEvidence] = $this->boundedPrompt($input->message, $grounding);
        $result = $this->gateway->execute(new AiExecutionRequest(
            context: $context,
            selection: $selection,
            operation: 'private_agent.select_evidence',
            userInput: $prompt,
            requestedTools: [$tool],
        ));

        $evidence = $this->validateEvidence($result->data, $visibleEvidence);
        if ($evidence === null) {
            Log::warning('Private tenant AI grounding rejected provider output.', [
                'tenant_id' => $input->tenantId,
                'correlation_id' => $context->correlationId,
                'capability' => 'private_tenant_agent',
            ]);

            return new ConversationEngineResult(
                payload: $this->presentFallback($fallback, $context),
                evidence: [],
                execution: $result,
                fallback: true,
                failureCode: 'ai_evidence_insufficient',
            );
        }

        $response = $this->applyEvidenceSelection($this->presentFallback($fallback, $context), $evidence);
        $response['fallback'] = false;
        $response['evidence'] = $evidence;
        $response['meta'] = [
            'mode' => 'grounded_tenant_private_agent',
            'llm_used' => true,
            'privacy_scope' => 'tenant_private_only',
            'correlation_id' => $result->correlationId,
            'provider' => $result->provider,
            'model' => $result->model,
        ];

        return new ConversationEngineResult(
            payload: $response,
            evidence: [],
            execution: $result,
            fallback: false,
        );
    }

    /** @return array<string, mixed> */
    public function fallback(PrivateConversationInput $input): array
    {
        return $this->presentFallback($this->reader->answer($input->tenantId, $input->message));
    }

    /**
     * @param  array<int, array<string, mixed>>  $grounding
     * @return array{string, array<string, array<string, mixed>>}
     */
    private function boundedPrompt(string $message, array $grounding): array
    {
        $max = max(1, (int) config('ai.limits.max_user_input_characters', 2000));
        $grounding = array_slice($grounding, 0, 20);

        do {
            $prompt = json_encode([
                'user_query_untrusted' => mb_substr($message, 0, 500),
                'authorized_tenant_evidence_untrusted' => $grounding,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (strlen($prompt) <= $max || count($grounding) <= 1) {
                break;
            }

            array_pop($grounding);
        } while ($grounding !== []);

        if (strlen($prompt) > $max) {
            throw new AiExecutionDeniedException('prompt_bounds');
        }

        $visible = [];
        foreach ($grounding as $item) {
            $id = $item['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $visible[$id] = $item;
            }
        }

        return [$prompt, $visible];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $visible
     * @return array<int, array{type:string,id:string,reason:string}>|null
     */
    private function validateEvidence(array $data, array $visible): ?array
    {
        $evidence = $data['evidence'] ?? null;
        if (! is_array($evidence) || $evidence === []) {
            return null;
        }

        $seen = [];
        $validated = [];
        foreach ($evidence as $entry) {
            if (! is_array($entry)) {
                return null;
            }

            $id = $entry['id'] ?? null;
            $type = $entry['type'] ?? null;
            if (! is_string($id) || ! is_string($type) || isset($seen[$id]) || ! isset($visible[$id])
                || ($visible[$id]['type'] ?? null) !== $type) {
                return null;
            }

            $seen[$id] = true;
            $validated[] = [
                'type' => $type,
                'id' => $id,
                'reason' => 'Dato verificado dentro del alcance privado autorizado.',
            ];
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<int, array{type:string,id:string,reason:string}>  $evidence
     * @return array<string, mixed>
     */
    private function applyEvidenceSelection(array $response, array $evidence): array
    {
        $items = $response['data']['items'] ?? null;
        if (! is_array($items)) {
            return $response;
        }

        $byId = [];
        foreach ($items as $item) {
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $byId[$item['id']] = $item;
            }
        }

        $selected = [];
        foreach ($evidence as $entry) {
            if (isset($byId[$entry['id']])) {
                $selected[] = $byId[$entry['id']];
            }
        }

        $response['data']['items'] = $selected;
        $response['answer'] = 'Encontre '.count($selected).' opciones relevantes y verificadas en el catalogo de este negocio.';

        return $response;
    }

    /** @param array<string, mixed> $fallback */
    private function presentFallback(array $fallback, ?AiExecutionContext $context = null): array
    {
        unset($fallback['grounding'], $fallback['tool'], $fallback['evidence']);
        $fallback['fallback'] = true;
        $meta = is_array($fallback['meta'] ?? null) ? $fallback['meta'] : [];
        $meta['mode'] = 'deterministic_tenant_private_agent';
        $meta['llm_used'] = false;
        $meta['privacy_scope'] = 'tenant_private_only';
        if ($context !== null) {
            $meta['correlation_id'] = $context->correlationId;
        }
        $fallback['meta'] = $meta;

        return $fallback;
    }
}
