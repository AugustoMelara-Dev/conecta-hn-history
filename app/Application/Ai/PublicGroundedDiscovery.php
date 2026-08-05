<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Conversation\ConversationEngineResult;
use App\Application\Ai\Conversation\ConversationMessageClaim;
use App\Application\Ai\Conversation\PublicConversationInput;
use App\Application\Ai\DTOs\AiExecutionContext;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\Grounding\GroundingEvidenceProjector;
use App\Application\Ai\Grounding\GroundingPolicy;
use App\Application\Ai\Grounding\GroundingValidator;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Discovery\PublishedDiscoveryGuideService;
use App\Application\Markets\MarketContext;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiMalformedResponseException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final class PublicGroundedDiscovery
{
    public function __construct(
        private readonly PublishedDiscoveryGuideService $guide,
        private readonly AiExecutionContextFactory $contexts,
        private readonly AiGateway $gateway,
        private readonly GroundingEvidenceProjector $evidenceProjector,
        private readonly GroundingValidator $grounding,
    ) {}

    public function answer(
        PublicConversationInput $input,
        MarketContext $market,
        AiRuntimeSelection $selection,
        ConversationMessageClaim $claim,
    ): ConversationEngineResult {
        $fallback = $this->deterministicFallback($input, $market);
        $items = $fallback['items'];

        if ($items === []) {
            return new ConversationEngineResult(
                payload: $this->asFallback($fallback, null),
                evidence: [],
                execution: null,
                fallback: true,
            );
        }

        $authorizedEvidence = $this->evidenceProjector->project(
            ownerTenantId: (string) config('ai.public.billing_tenant_id'),
            requestMessageId: $claim->requestMessage->id,
            visibility: 'public',
            items: $items,
        );
        $authorizedIds = collect($authorizedEvidence)->pluck('evidenceId')->all();
        $items = collect($items)
            ->filter(static fn (array $item): bool => in_array($item['evidence_id'] ?? null, $authorizedIds, true))
            ->values()
            ->all();
        if ($items === []) {
            $fallback['items'] = [];
            $fallback['answer'] = 'No encontre una coincidencia publica suficiente.';

            return new ConversationEngineResult(
                payload: $this->asFallback($fallback, null),
                evidence: [],
                execution: null,
                fallback: true,
            );
        }
        $fallback['items'] = $items;
        $fallback['answer'] = $this->canonicalAnswer($items, $input->budget, $market);
        $fallback['suggestions'] = $this->suggestions($items);

        $context = $this->contexts->create(
            capability: 'public_discovery',
            rateLimitIdentity: 'public-directory:'.$input->rateLimitIdentity,
            actorId: $input->actorId,
            authorized: true,
            entitled: true,
            allowedTools: ['public_catalog.search'],
            correlationId: $claim->requestMessage->id,
            conversationId: $claim->conversationId,
            requestMessageId: $claim->requestMessage->id,
        );

        $evidence = $this->evidencePayload($items);
        [$userPrompt, $visibleEvidenceIds] = $this->boundedUserPrompt(
            $input->message,
            $input->budget,
            $input->city,
            $input->category,
            $evidence,
        );
        $result = $this->gateway->execute(new AiExecutionRequest(
            context: $context,
            selection: $selection,
            operation: 'public_discovery.answer',
            userInput: $userPrompt,
            requestedTools: ['public_catalog.search'],
        ));

        try {
            $validated = $this->grounding->validate(
                $result->data,
                $authorizedEvidence,
                new GroundingPolicy(
                    tenantId: (string) config('ai.public.billing_tenant_id'),
                    visibility: 'public',
                    currency: strtoupper($market->currencyCode),
                    freshnessCutoff: CarbonImmutable::now()->subDays(
                        max(1, (int) config('ai.knowledge.freshness_days', 30)),
                    ),
                    allowedClaimTypes: ['product', 'service'],
                ),
            );
        } catch (AiMalformedResponseException) {
            $validated = null;
        }
        $grounded = $validated === null
            ? null
            : $this->ground($result->data, $items, $visibleEvidenceIds, $input->budget, $market);
        if ($grounded === null) {
            Log::warning('Public discovery grounding rejected provider output.', [
                'correlation_id' => $context->correlationId,
                'capability' => 'public_discovery',
            ]);

            return new ConversationEngineResult(
                payload: $this->asFallback($fallback, $context),
                evidence: [],
                execution: $result,
                fallback: true,
                failureCode: 'ai_evidence_insufficient',
            );
        }

        return new ConversationEngineResult(
            payload: [
                'answer' => $grounded['answer'],
                'items' => $grounded['items'],
                'suggestions' => $this->suggestions($grounded['items']),
                'source' => 'public_directory',
                'fallback' => false,
                'evidence' => $grounded['evidence'],
                'meta' => [
                    'mode' => 'grounded_public_discovery',
                    'llm_used' => true,
                    'privacy_scope' => 'public_directory_only',
                    'correlation_id' => $result->correlationId,
                    'provider' => $result->provider,
                    'model' => $result->model,
                ],
            ],
            evidence: $validated->selectedEvidence,
            execution: $result,
            fallback: false,
        );
    }

    /** @return array<string, mixed> */
    public function fallback(PublicConversationInput $input, MarketContext $market): array
    {
        return $this->asFallback($this->deterministicFallback($input, $market), null);
    }

    /** @return array<string, mixed> */
    private function deterministicFallback(PublicConversationInput $input, MarketContext $market): array
    {
        return $this->guide->answer($input, $market);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function evidencePayload(array $items): array
    {
        return collect($items)->take(6)->map(static fn (array $item): array => [
            'evidence_id' => mb_substr((string) ($item['evidence_id'] ?? ''), 0, 64),
            'type' => mb_substr((string) ($item['type'] ?? ''), 0, 16),
            'name' => mb_substr((string) ($item['name'] ?? ''), 0, 100),
            'description' => mb_substr((string) ($item['description'] ?? ''), 0, 120),
            'price' => isset($item['price']) ? (float) $item['price'] : null,
            'price_label' => mb_substr((string) ($item['price_label'] ?? ''), 0, 32),
            'business_name' => mb_substr((string) ($item['business_name'] ?? ''), 0, 100),
            'business_url' => mb_substr((string) ($item['business_url'] ?? ''), 0, 160),
            'availability' => mb_substr((string) ($item['availability'] ?? 'unknown'), 0, 16),
        ])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @return array{string, list<string>}
     */
    private function boundedUserPrompt(
        string $message,
        ?float $budget,
        ?string $city,
        ?string $category,
        array $evidence,
    ): array {
        $maxCharacters = max(512, (int) config('ai.limits.max_user_input_characters', 2000));

        do {
            $prompt = $this->userPrompt($message, $budget, $city, $category, $evidence);
            if (mb_strlen($prompt) <= $maxCharacters || count($evidence) <= 1) {
                break;
            }

            array_pop($evidence);
        } while ($evidence !== []);

        if (mb_strlen($prompt) > $maxCharacters) {
            throw new AiExecutionDeniedException('prompt_bounds');
        }

        $ids = collect($evidence)
            ->pluck('evidence_id')
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        return [$prompt, $ids];
    }

    /** @param array<int, array<string, mixed>> $evidence */
    private function userPrompt(
        string $message,
        ?float $budget,
        ?string $city,
        ?string $category,
        array $evidence,
    ): string {
        return json_encode([
            'user_query_untrusted' => mb_substr($message, 0, 500),
            'filters' => [
                'budget' => $budget,
                'city' => $city,
                'category' => $category,
            ],
            'eligible_catalog_evidence_untrusted' => $evidence,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     * @param  list<string>  $visibleEvidenceIds
     * @return array{answer:string,items:array<int,array<string,mixed>>,evidence:array<int,array{type:string,id:string,reason:string}>}|null
     */
    private function ground(
        array $data,
        array $items,
        array $visibleEvidenceIds,
        ?float $budget,
        MarketContext $market,
    ): ?array {
        $answer = $data['answer'] ?? null;
        $evidence = $data['evidence'] ?? null;

        if (! is_string($answer) || trim($answer) === '' || ! is_array($evidence) || $evidence === []) {
            return null;
        }

        $byEvidenceId = [];
        foreach ($items as $item) {
            $evidenceId = $item['evidence_id'] ?? null;
            if (is_string($evidenceId) && in_array($evidenceId, $visibleEvidenceIds, true)) {
                $byEvidenceId[$evidenceId] = $item;
            }
        }

        $selected = [];
        $validatedEvidence = [];
        foreach ($evidence as $entry) {
            if (! is_array($entry)) {
                return null;
            }

            $type = $entry['type'] ?? null;
            $id = $entry['id'] ?? null;
            $reason = $entry['reason'] ?? null;

            if (! is_string($type) || ! is_string($id) || ! is_string($reason)
                || trim($reason) === '' || isset($selected[$id]) || ! isset($byEvidenceId[$id])
                || ($byEvidenceId[$id]['type'] ?? null) !== $type) {
                return null;
            }

            $selected[$id] = $byEvidenceId[$id];
            $validatedEvidence[] = [
                'type' => $type,
                'id' => $id,
                'reason' => $this->canonicalReason($byEvidenceId[$id], $budget),
            ];
        }

        $selectedItems = array_values($selected);

        return [
            'answer' => $this->canonicalAnswer($selectedItems, $budget, $market),
            'items' => $selectedItems,
            'evidence' => $validatedEvidence,
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function canonicalAnswer(array $items, ?float $budget, MarketContext $market): string
    {
        $currency = strtoupper($market->currencyCode);
        $currencyLabel = $currency === 'HNL' ? 'L.' : $currency;
        $prefix = $budget === null
            ? 'Encontre estas opciones publicadas y verificables:'
            : 'Encontre estas opciones publicadas dentro de tu presupuesto de '.$currencyLabel.' '.number_format($budget, 2).':';
        $options = collect($items)->take(3)->map(static fn (array $item): string => sprintf(
            '%s en %s por %s',
            (string) ($item['name'] ?? 'Opcion publicada'),
            (string) ($item['business_name'] ?? 'Negocio local'),
            (string) ($item['price_label'] ?? 'precio por confirmar'),
        ))->implode('; ');

        return $prefix.' '.$options.'.';
    }

    /** @param array<string, mixed> $item */
    private function canonicalReason(array $item, ?float $budget): string
    {
        $reason = 'Precio publicado: '.(string) ($item['price_label'] ?? 'por confirmar')
            .'. Disponibilidad publica: '.(string) ($item['availability'] ?? 'por confirmar').'.';

        return $budget === null ? $reason : $reason.' Esta dentro del presupuesto indicado.';
    }

    /** @param array<int, array<string, mixed>> $items */
    private function suggestions(array $items): array
    {
        return collect($items)->map(static fn (array $item): array => [
            'label' => 'Ver '.(string) ($item['business_name'] ?? 'negocio'),
            'href' => (string) ($item['business_url'] ?? '/directorio'),
        ])->unique('href')->values()->all();
    }

    /** @param array<string, mixed> $fallback */
    private function asFallback(array $fallback, ?AiExecutionContext $context): array
    {
        $fallback['source'] = 'public_directory';
        $fallback['fallback'] = true;
        $meta = is_array($fallback['meta'] ?? null) ? $fallback['meta'] : [];
        $meta['mode'] = $meta['mode'] ?? 'deterministic_public_projection';
        $meta['llm_used'] = false;
        $meta['privacy_scope'] = 'public_directory_only';

        if ($context !== null) {
            $meta['correlation_id'] = $context->correlationId;
        }

        $fallback['meta'] = $meta;
        unset($fallback['evidence']);

        return $fallback;
    }
}
