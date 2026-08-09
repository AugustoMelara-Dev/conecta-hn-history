<?php

declare(strict_types=1);

use App\Application\Ai\AssistantExecutor;
use App\Application\Ai\Assistants\ContactEnrichmentAssistant;
use App\Application\Ai\Assistants\DashboardNarrativeAssistant;
use App\Application\Ai\Assistants\DealEmailDraftAssistant;
use App\Application\Ai\Assistants\EmailReplyAssistant;
use App\Application\Ai\Assistants\InvoiceMemoAssistant;
use App\Application\Ai\Assistants\LeadScoringAssistant;
use App\Application\Ai\Assistants\NextBestActionAssistant;
use App\Application\Ai\Assistants\ProductDescriptionAssistant;
use App\Application\Ai\Assistants\RefundJustificationAssistant;
use App\Application\Ai\Assistants\ReportInsightAssistant;
use App\Application\Ai\Assistants\SentimentAssistant;
use App\Application\Ai\Assistants\TaxCategoryAssistant;
use App\Application\Ai\Assistants\TranslationAssistant;
use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\Contracts\AssistantRuntime;
use App\Application\Ai\DTOs\AssistantExecutionContext;
use Illuminate\Support\Str;

function assistantContext(): AssistantExecutionContext
{
    return new AssistantExecutionContext(
        tenantId: (string) Str::uuid(),
        actorId: (string) Str::uuid(),
        correlationId: (string) Str::uuid(),
    );
}

function deterministicAssistantRuntime(): AssistantRuntime
{
    return new class implements AssistantRuntime
    {
        public function execute(string $assistantKey, array $input, AssistantExecutionContext $context): AssistantResult
        {
            return AssistantResult::success([
                'summary' => 'Deterministic test result',
                'fields' => [['key' => 'assistant_key', 'value' => $assistantKey]],
                'confidence' => 1.0,
            ], metadata: ['status' => 'completed', 'correlation_id' => $context->correlationId]);
        }
    };
}

it('registers all 13 assistants with the canonical runtime contract', function () {
    $executor = new AssistantExecutor;
    $runtime = deterministicAssistantRuntime();
    $classes = [
        DealEmailDraftAssistant::class, ContactEnrichmentAssistant::class, LeadScoringAssistant::class,
        ProductDescriptionAssistant::class, InvoiceMemoAssistant::class, RefundJustificationAssistant::class,
        DashboardNarrativeAssistant::class, EmailReplyAssistant::class, SentimentAssistant::class,
        TranslationAssistant::class, TaxCategoryAssistant::class, NextBestActionAssistant::class,
        ReportInsightAssistant::class,
    ];
    foreach ($classes as $class) {
        $executor->register(new $class($runtime));
    }

    expect($executor->all())->toHaveCount(13);
});

it('fails closed without tenant actor and correlation context', function () {
    $assistant = new DealEmailDraftAssistant(deterministicAssistantRuntime());

    expect($assistant->execute(['deal_id' => 'deal-1'])->success)->toBeFalse()
        ->and($assistant->execute(['deal_id' => 'deal-1'])->metadata['status'])->toBe('unavailable');
});

it('uses a deterministic fake only in tests and preserves validation', function () {
    $assistant = new LeadScoringAssistant(deterministicAssistantRuntime());

    $success = $assistant->execute(['contact_id' => 'contact-1'], assistantContext());
    expect($success->success)->toBeTrue()
        ->and($success->output['summary'])->toBe('Deterministic test result')
        ->and($assistant->execute([], assistantContext())->success)->toBeFalse();
});

it('requires translation input before invoking the provider runtime', function () {
    $assistant = new TranslationAssistant(deterministicAssistantRuntime());

    expect($assistant->execute([], assistantContext())->success)->toBeFalse()
        ->and($assistant->execute(['text' => 'Hola', 'target_lang' => 'en'], assistantContext())->success)->toBeTrue();
});
