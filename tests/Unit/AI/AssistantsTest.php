<?php

declare(strict_types=1);

use App\Application\AI\AssistantExecutor;
use App\Application\AI\Assistants\ContactEnrichmentAssistant;
use App\Application\AI\Assistants\DashboardNarrativeAssistant;
use App\Application\AI\Assistants\DealEmailDraftAssistant;
use App\Application\AI\Assistants\EmailReplyAssistant;
use App\Application\AI\Assistants\InvoiceMemoAssistant;
use App\Application\AI\Assistants\LeadScoringAssistant;
use App\Application\AI\Assistants\NextBestActionAssistant;
use App\Application\AI\Assistants\ProductDescriptionAssistant;
use App\Application\AI\Assistants\RefundJustificationAssistant;
use App\Application\AI\Assistants\ReportInsightAssistant;
use App\Application\AI\Assistants\SentimentAssistant;
use App\Application\AI\Assistants\TaxCategoryAssistant;
use App\Application\AI\Assistants\TranslationAssistant;

it('AssistantExecutor registers all 13 assistants', function () {
    $executor = new AssistantExecutor;
    $classes = [
        DealEmailDraftAssistant::class,
        ContactEnrichmentAssistant::class,
        LeadScoringAssistant::class,
        ProductDescriptionAssistant::class,
        InvoiceMemoAssistant::class,
        RefundJustificationAssistant::class,
        DashboardNarrativeAssistant::class,
        EmailReplyAssistant::class,
        SentimentAssistant::class,
        TranslationAssistant::class,
        TaxCategoryAssistant::class,
        NextBestActionAssistant::class,
        ReportInsightAssistant::class,
    ];
    foreach ($classes as $class) {
        $executor->register(new $class);
    }
    expect(count($executor->all()))->toBe(13);
});

it('DealEmailDraftAssistant requires deal_id', function () {
    $assistant = new DealEmailDraftAssistant;
    expect($assistant->key())->toBe('deal_email_draft');
    expect($assistant->estimateCost())->toBe(0.05);
    expect($assistant->category())->toBe('CRM');
    expect($assistant->guardrails())->toBeArray();

    $result = $assistant->execute([]);
    expect($result->success)->toBeFalse();

    $result = $assistant->execute(['deal_id' => 'test-123']);
    expect($result->success)->toBeTrue();
});

it('LeadScoringAssistant returns score structure', function () {
    $assistant = new LeadScoringAssistant;
    $result = $assistant->execute(['contact_id' => 'abc']);
    expect($result->success)->toBeTrue();
    expect($result->output)->toHaveKey('score');
    expect($result->output)->toHaveKey('tier');
});

it('TranslationAssistant requires text + target_lang', function () {
    $assistant = new TranslationAssistant;
    $result = $assistant->execute(['text' => 'Hola mundo', 'target_lang' => 'en']);
    expect($result->success)->toBeTrue();

    $result = $assistant->execute([]);
    expect($result->success)->toBeFalse();
});

it('AssistantExecutor.execute calls assistant and returns result', function () {
    $executor = new AssistantExecutor;
    $executor->register(new LeadScoringAssistant);

    $result = $executor->execute('lead_scoring', ['contact_id' => 'test']);
    expect($result->success)->toBeTrue();
});
