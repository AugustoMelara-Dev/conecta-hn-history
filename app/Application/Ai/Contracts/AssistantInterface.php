<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

/**
 * AssistantInterface - Cada AI Assistant implementa este contrato.
 */
interface AssistantInterface
{
    /**
     * Key única del assistant (e.g. 'deal_email_draft').
     */
    public function key(): string;

    /**
     * Nombre legible del assistant.
     */
    public function name(): string;

    /**
     * Descripción corta.
     */
    public function description(): string;

    /**
     * Ejecuta el assistant con el input dado.
     *
     * @param  array<string, mixed>  $input
     * @return AssistantResult
     */
    public function execute(array $input): AssistantResult;

    /**
     * Costo estimado en USD por ejecución.
     */
    public function estimateCost(): float;

    /**
     * Guardrails: array de reglas que el assistant respeta.
     *
     * @return array<int, string>
     */
    public function guardrails(): array;

    /**
     * Categoría del assistant para agrupación en UI.
     */
    public function category(): string;
}
