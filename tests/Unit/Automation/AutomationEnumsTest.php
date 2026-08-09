<?php

declare(strict_types=1);

use App\Application\Automation\TemplateCatalog;
use App\Domain\Automation\Enums\ActionType;
use App\Domain\Automation\Enums\TriggerType;
use App\Domain\Automation\ValueObjects\AutomationGraph;

it('TriggerType enum has 7 cases with labels', function () {
    $cases = TriggerType::cases();
    expect(count($cases))->toBe(7);
    foreach ($cases as $case) {
        expect($case->label())->toBeString();
        expect($case->icon())->toBeString()->not->toBeEmpty();
    }
});

it('ActionType enum has 10 cases with icons and colors', function () {
    $cases = ActionType::cases();
    expect(count($cases))->toBe(10);
    foreach ($cases as $case) {
        expect($case->label())->toBeString();
        expect($case->icon())->toBeString();
        expect($case->color())->toBeIn(['success', 'info', 'primary', 'warning', 'gray']);
    }
});

it('AutomationGraph fromJson/toJson roundtrip', function () {
    $original = new AutomationGraph(
        nodes: [['id' => 't1', 'type' => 'trigger', 'trigger_type' => 'manual_run']],
        edges: [],
    );
    $json = $original->toJson();
    $parsed = AutomationGraph::fromJson($json);

    expect($parsed->nodeCount())->toBe(1);
    expect($parsed->isEmpty())->toBeFalse();
});

it('TemplateCatalog has 12 templates covering different categories', function () {
    $catalog = new TemplateCatalog;
    $templates = $catalog->all();
    expect(count($templates))->toBe(12);
    $categories = array_unique(array_column($templates, 'category'));
    expect(count($categories))->toBeGreaterThanOrEqual(6);
});

it('Each template graph has valid structure', function () {
    $catalog = new TemplateCatalog;
    foreach ($catalog->all() as $template) {
        $graph = $template['graph'];
        expect($graph)->toHaveKeys(['nodes', 'edges']);
        expect(count($graph['nodes']))->toBeGreaterThan(0);
        // First node should be trigger
        expect($graph['nodes'][0]['type'])->toBe('trigger');
    }
});
