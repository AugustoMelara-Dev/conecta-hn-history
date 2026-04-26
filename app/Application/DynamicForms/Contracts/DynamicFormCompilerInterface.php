<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

use App\Application\DynamicForms\Data\DynamicSchemaData;
use Filament\Schemas\Components\Component;

interface DynamicFormCompilerInterface
{
    /**
     * @return array<int, Component>
     */
    public function compileFormSchema(DynamicSchemaData $schema, string $statePrefix = 'payload_json'): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function compileValidationRulesFromData(DynamicSchemaData $schema, array $payload = []): array;
}
