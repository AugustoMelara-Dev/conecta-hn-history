<?php

declare(strict_types=1);

use App\Application\Automation\GraphBuilder;

it('validates a simple graph with trigger + one action', function () {
    $builder = new GraphBuilder();

    $result = $builder->build(
        nodes: [
            ['id' => 't1', 'type' => 'trigger', 'trigger_type' => 'record_created'],
            ['id' => 'a1', 'type' => 'action', 'action_type' => 'send_email'],
        ],
        edges: [['from' => 't1', 'to' => 'a1']],
    );

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
    expect($result['graph'])->not->toBeNull();
    expect($result['graph']->nodeCount())->toBe(2);
});

it('rejects graph without trigger', function () {
    $builder = new GraphBuilder();

    $result = $builder->build(
        nodes: [
            ['id' => 'a1', 'type' => 'action', 'action_type' => 'send_email'],
        ],
        edges: [],
    );

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('Workflow must have at least one trigger node');
});

it('rejects graph with cycle', function () {
    $builder = new GraphBuilder();

    $result = $builder->build(
        nodes: [
            ['id' => 't1', 'type' => 'trigger', 'trigger_type' => 'manual_run'],
            ['id' => 'a1', 'type' => 'action', 'action_type' => 'send_email'],
            ['id' => 'a2', 'type' => 'action', 'action_type' => 'send_sms'],
        ],
        edges: [
            ['from' => 't1', 'to' => 'a1'],
            ['from' => 'a1', 'to' => 'a2'],
            ['from' => 'a2', 'to' => 'a1'], // cycle
        ],
    );

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('Workflow graph contains a cycle');
});

it('rejects graph with multiple triggers', function () {
    $builder = new GraphBuilder();

    $result = $builder->build(
        nodes: [
            ['id' => 't1', 'type' => 'trigger', 'trigger_type' => 'manual_run'],
            ['id' => 't2', 'type' => 'trigger', 'trigger_type' => 'record_created'],
            ['id' => 'a1', 'type' => 'action', 'action_type' => 'send_email'],
        ],
        edges: [
            ['from' => 't1', 'to' => 'a1'],
            ['from' => 't2', 'to' => 'a1'],
        ],
    );

    expect($result['valid'])->toBeFalse();
});

it('rejects orphan action nodes', function () {
    $builder = new GraphBuilder();

    $result = $builder->build(
        nodes: [
            ['id' => 't1', 'type' => 'trigger', 'trigger_type' => 'manual_run'],
            ['id' => 'a1', 'type' => 'action', 'action_type' => 'send_email'],
            ['id' => 'a2', 'type' => 'action', 'action_type' => 'send_sms'], // orphan
        ],
        edges: [['from' => 't1', 'to' => 'a1']],
    );

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain("Action node 'a2' is not connected");
});

it('rejects duplicate node IDs', function () {
    $builder = new GraphBuilder();

    $result = $builder->build(
        nodes: [
            ['id' => 'n1', 'type' => 'trigger', 'trigger_type' => 'manual_run'],
            ['id' => 'n1', 'type' => 'action', 'action_type' => 'send_email'], // duplicate
        ],
        edges: [['from' => 'n1', 'to' => 'n1']],
    );

    expect($result['valid'])->toBeFalse();
});
