<?php

declare(strict_types=1);

use App\Application\AI\AssistantExecutor;

it('AssistantExecutor registers all 13 assistants', function () {
    $executor = new AssistantExecutor();
    $classes = [
        \App\Application\AI\Assistants\DealEmailDraftAssistant::class,
        \App\Application\AI\Assistants\ContactEnrichmentAssistant::class,
        \App\Application\AI\Assistants\LeadScoringAssistant::class,
        \App\Application\AI\Assistants\ProductDescriptionAssistant::class,
        \App\Application\AI\Assistants\InvoiceMemoAssistant::class,
        \App\Application\AI\Assistants\RefundJustificationAssistant::class,
        \App\Application\AI\Assistants\DashboardNarrativeAssistant::class,
        \App\Application\AI\Assistants\EmailReplyAssistant::class,
        \App\Application\AI\Assistants\SentimentAssistant::class,
        \App\Application\AI\Assistants\TranslationAssistant::class,
        \App\Application\AI\Assistants\TaxCategoryAssistant::class,
        \App\Application\AI\Assistants\NextBestActionAssistant::class,
        \App\Application\AI\Assistants\ReportInsightAssistant::class,
    ];
    foreach ($classes as $class) {
        $executor->register(new $class());
    }
    expect(count($executor->all()))->toBe(13);
});

it('DealEmailDraftAssistant requires deal_id', function () {
    $assistant = new \App\Application\AI\Assistants\DealEmailDraftAssistant();
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
    $assistant = new \App\Application\AI\Assistants\LeadScoringAssistant();
    $result = $assistant->execute(['contact_id' => 'abc']);
    expect($result->success)->toBeTrue();
    expect($result->output)->toHaveKey('score');
    expect($result->output)->toHaveKey('tier');
});

it('TranslationAssistant requires text + target_lang', function () {
    $assistant = new \App\Application\AI\Assistants\TranslationAssistant();
    $result = $assistant->execute(['text' => 'Hola mundo', 'target_lang' => 'en']);
    expect($result->success)->toBeTrue();

    $result = $assistant->execute([]);
    expect($result->success)->toBeFalse();
});

it('AssistantExecutor.execute calls assistant and returns result', function () {
    $executor = new AssistantExecutor();
    $executor->register(new \App\Application\AI\Assistants\LeadScoringAssistant());

    $result = $executor->execute('lead_scoring', ['contact_id' => 'test']);
    expect($result->success)->toBeTrue();
});
