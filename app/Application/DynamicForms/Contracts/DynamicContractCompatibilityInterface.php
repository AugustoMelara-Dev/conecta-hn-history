<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

interface DynamicContractCompatibilityInterface
{
    /**
     * @param  array<string, mixed>  $candidateSchema
     * @param  array<string, mixed>|null  $activeSchema
     * @return array<string, mixed>
     */
    public function normalizeAndAssertSchema(array $candidateSchema, ?array $activeSchema = null): array;

    /**
     * @param  array<string, mixed>  $candidateTemplate
     * @param  array<string, mixed>|null  $activeTemplate
     * @return array<string, mixed>
     */
    public function normalizeAndAssertTemplate(array $candidateTemplate, ?array $activeTemplate = null): array;
}

