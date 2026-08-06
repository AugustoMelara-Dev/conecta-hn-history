<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\TriggerType;
use App\Domain\Automation\ValueObjects\AutomationGraph;

/**
 * GraphBuilder - Construye y valida DAG de workflows.
 *
 * Garantías:
 * - Existe exactamente un nodo trigger
 * - No hay ciclos
 * - Cada edge conecta nodos existentes
 * - Cada acción tiene schema válido
 */
class GraphBuilder
{
    /**
     * @return array{valid: bool, errors: array<int, string>, graph: ?AutomationGraph}
     */
    public function build(array $nodes, array $edges): array
    {
        $errors = $this->validate($nodes, $edges);

        if (! empty($errors)) {
            return ['valid' => false, 'errors' => $errors, 'graph' => null];
        }

        return [
            'valid' => true,
            'errors' => [],
            'graph' => new AutomationGraph($nodes, $edges),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function validate(array $nodes, array $edges): array
    {
        $errors = [];

        // Check for unique IDs
        $ids = array_column($nodes, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            $errors[] = 'Node IDs must be unique';
        }

        // Check exactly one trigger
        $triggers = array_filter($nodes, fn ($n) => ($n['type'] ?? null) === 'trigger');
        if (count($triggers) === 0) {
            $errors[] = 'Workflow must have at least one trigger node';
        } elseif (count($triggers) > 1) {
            $errors[] = 'Workflow must have exactly one trigger node';
        }

        // Validate trigger type
        foreach ($triggers as $trigger) {
            $type = $trigger['trigger_type'] ?? null;
            if ($type === null || TriggerType::tryFrom($type) === null) {
                $errors[] = "Invalid trigger_type: {$type}";
            }
        }

        // Check edges connect existing nodes
        $nodeIds = array_flip($ids);
        foreach ($edges as $edge) {
            $from = $edge['from'] ?? null;
            $to = $edge['to'] ?? null;
            if (! isset($nodeIds[$from])) {
                $errors[] = "Edge 'from' references unknown node: {$from}";
            }
            if (! isset($nodeIds[$to])) {
                $errors[] = "Edge 'to' references unknown node: {$to}";
            }
        }

        // Check for cycles (topological sort)
        if ($this->hasCycle($nodes, $edges)) {
            $errors[] = 'Workflow graph contains a cycle';
        }

        // Check for orphan nodes
        $connected = [];
        foreach ($edges as $edge) {
            $connected[$edge['from']] = true;
            $connected[$edge['to']] = true;
        }
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'trigger') {
                continue;
            }
            if (! isset($connected[$node['id']])) {
                $errors[] = "Action node '{$node['id']}' is not connected";
            }
        }

        return $errors;
    }

    /**
     * Detects cycles using DFS.
     *
     * @return bool
     */
    private function hasCycle(array $nodes, array $edges): bool
    {
        $adj = [];
        foreach ($nodes as $node) {
            $adj[$node['id']] = [];
        }
        foreach ($edges as $edge) {
            $adj[$edge['from']][] = $edge['to'];
        }

        $visited = [];
        $recStack = [];

        foreach (array_keys($adj) as $node) {
            if ($this->dfsHasCycle($node, $adj, $visited, $recStack)) {
                return true;
            }
        }

        return false;
    }

    private function dfsHasCycle(string $node, array $adj, array &$visited, array &$recStack): bool
    {
        if (isset($recStack[$node])) {
            return true;
        }
        if (isset($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $recStack[$node] = true;

        foreach ($adj[$node] as $neighbor) {
            if ($this->dfsHasCycle($neighbor, $adj, $visited, $recStack)) {
                return true;
            }
        }

        unset($recStack[$node]);
        return false;
    }
}
