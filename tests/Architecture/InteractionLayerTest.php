<?php

declare(strict_types=1);

/**
 * InteractionLayerTest - DTO Residency & Dependency Governance
 *
 * Enforces:
 * 1. InteractionData DTO resides in Application layer (not Domain)
 * 2. RecordInteractionAction imports from Application layer
 */
test('interaction data dto resides in application layer', function () {
    expect(class_exists(\App\Application\Interactions\DTOs\InteractionData::class))->toBeTrue();
    expect(class_exists('App\Domain\Interactions\DTOs\InteractionData'))->toBeFalse();
});

test('record interaction action depends on application layer dto', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Interactions/RecordInteractionAction.php');
    expect($content)
        ->toContain('use App\Application\Interactions\DTOs\InteractionData;')
        ->not->toContain('use App\Domain\Interactions\DTOs\InteractionData;');
});
