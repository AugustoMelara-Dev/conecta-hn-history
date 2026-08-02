<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Conversation\ConversationEngineResult;
use App\Application\Ai\Conversation\ConversationMessageClaim;
use App\Application\Ai\Conversation\PrivateConversationInput;
use App\Application\Ai\DTOs\AiExecutionContext;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\Grounding\GroundingEvidenceProjector;
use App\Application\Ai\Grounding\GroundingPolicy;
use App\Application\Ai\Grounding\GroundingValidator;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Workforce\WorkforceAgentContext;
use App\Application\Ai\Workforce\WorkforceAgentResolver;
use App\Application\BusinessGraph\Contracts\BusinessGraphRepositoryInterface;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Infrastructure\AI\EloquentTenantAgentReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final class PrivateGroundedAgent
{
    public function __construct(
        private readonly EloquentTenantAgentReader $reader,
        private readonly AiExecutionContextFactory $contexts,
        private readonly AiGateway $gateway,
        private readonly GroundingEvidenceProjector $evidenceProjector,
        private readonly GroundingValidator $groundingValidator,
        private readonly WorkforceAgentResolver $workforce,
        private readonly BusinessGraphRepositoryInterface $businessGraph,
    ) {}

    public function answer(
        PrivateConversationInput $input,
        AiRuntimeSelection $selection,
        ConversationMessageClaim $claim,
        ?WorkforceAgentContext $workforceContext = null,
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

        $authorizedEvidence = $this->evidenceProjector->project(
            ownerTenantId: $input->tenantId,
            requestMessageId: $claim->requestMessage->id,
            visibility: 'private',
            items: $grounding,
        );
        $authorizedIds = collect($authorizedEvidence)->pluck('evidenceId')->all();
        $grounding = collect($grounding)
            ->filter(static fn (array $item): bool => in_array($item['id'] ?? null, $authorizedIds, true))
            ->values()
            ->all();
        if (is_array($fallback['data']['items'] ?? null)) {
            $fallback['data']['items'] = collect($fallback['data']['items'])
                ->filter(static fn (array $item): bool => in_array($item['id'] ?? null, $authorizedIds, true))
                ->values()
                ->all();
        }
        $fallback['grounding'] = $grounding;
        if ($grounding === []) {
            $fallback['answer'] = 'No hay evidencia vigente suficiente para esta consulta.';

            return new ConversationEngineResult(
                payload: $this->presentFallback($fallback),
                evidence: [],
                execution: null,
                fallback: true,
            );
        }

        $workforceContext ??= $this->workforce->resolve(
            tenantId: $input->tenantId,
            agentVersionId: $selection->agentVersionId,
        );
        if (! in_array($tool, $workforceContext->configuration->allowedJobs, true)) {
            throw new AiExecutionDeniedException('workforce_tool_policy');
        }
        $businessContext = $workforceContext->systemContext(
            $this->businessGraph->snapshot($input->tenantId),
        );

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
            workforceContext: $workforceContext,
        );
        [$prompt, $visibleEvidence] = $this->boundedPrompt($input->message, $grounding, $businessContext);
        $result = $this->gateway->execute(new AiExecutionRequest(
            context: $context,
            selection: $selection,
            operation: 'private_agent.select_evidence',
            userInput: $prompt,
            requestedTools: [$tool],
        ));

        try {
            $validated = $this->groundingValidator->validate(
                $result->data,
                $authorizedEvidence,
                new GroundingPolicy(
                    tenantId: $input->tenantId,
                    visibility: 'private',
                    currency: $authorizedEvidence[0]->currency ?? (string) ($fallback['meta']['currency'] ?? ''),
                    freshnessCutoff: CarbonImmutable::now()->subDays(
                        max(1, (int) config('ai.knowledge.freshness_days', 30)),
                    ),
                    allowedClaimTypes: ['product', 'service'],
                ),
            );
        } catch (AiMalformedResponseException) {
            $validated = null;
        }
        $evidence = $validated === null
            ? null
            : $this->validateEvidence($result->data, $visibleEvidence);
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
        $response['meta'] = array_merge(is_array($response['meta'] ?? null) ? $response['meta'] : [], [
            'mode' => 'grounded_tenant_private_agent',
            'llm_used' => true,
            'privacy_scope' => 'tenant_private_only',
            'correlation_id' => $result->correlationId,
            'provider' => $result->provider,
            'model' => $result->model,
        ]);

        return new ConversationEngineResult(
            payload: $response,
            evidence: $validated->selectedEvidence,
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
    private function boundedPrompt(string $message, array $grounding, array $workforceContext = []): array
    {
        $max = max(1, (int) config('ai.limits.max_user_input_characters', 2000));
        $grounding = array_slice($grounding, 0, 20);
        $businessContext = is_array($workforceContext['businessContext'] ?? null)
            ? $workforceContext['businessContext']
            : [];
        $workforceContext['businessContext'] = [
            'facts' => array_slice((array) ($businessContext['facts'] ?? []), 0, 10),
            'edges' => array_slice((array) ($businessContext['edges'] ?? []), 0, 10),
        ];

        do {
            $prompt = json_encode([
                'user_query_untrusted' => mb_substr($message, 0, 500),
                'authorized_tenant_evidence_untrusted' => $grounding,
                'workforce_context' => $workforceContext,
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
