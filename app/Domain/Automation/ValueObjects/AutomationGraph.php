<?php

declare(strict_types=1);

namespace App\Domain\Automation\ValueObjects;

/**
 * AutomationGraph - Representa el DAG (Directed Acyclic Graph) de un workflow.
 *
 * Estructura JSON:
 * {
 *   "nodes": [
 *     {"id": "n1", "type": "trigger", "trigger_type": "record_created", "config": {...}},
 *     {"id": "n2", "type": "action", "action_type": "send_email", "config": {...}}
 *   ],
 *   "edges": [
 *     {"from": "n1", "to": "n2"}
 *   ]
 * }
 */
final readonly class AutomationGraph
{
    public function __construct(
        public array $nodes,
        public array $edges,
    ) {}

    public function toArray(): array
    {
        return ['nodes' => $this->nodes, 'edges' => $this->edges];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nodes: $data['nodes'] ?? [],
            edges: $data['edges'] ?? [],
        );
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    public function isEmpty(): bool
    {
        return empty($this->nodes);
    }

    public function triggerNode(): ?array
    {
        foreach ($this->nodes as $node) {
            if (($node['type'] ?? null) === 'trigger') {
                return $node;
            }
        }

        return null;
    }

    public function actionNodes(): array
    {
        return array_values(array_filter($this->nodes, fn ($n) => ($n['type'] ?? null) === 'action'));
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    public function edgeCount(): int
    {
        return count($this->edges);
    }
}
