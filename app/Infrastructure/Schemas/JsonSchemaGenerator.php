<?php

declare(strict_types=1);

namespace App\Infrastructure\Schemas;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * JsonSchemaGenerator - Reflection-Based Contract Derivation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Converts Command DTOs into JSON Schema for contract testing.  ║
 * ║  METHOD:  Pure Reflection — no external dependencies.                   ║
 * ║  SCOPE:   CI gate to prevent silent breaking changes in Command shapes. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @see tests/Architecture/ContractValidationTest.php
 */
final class JsonSchemaGenerator
{
    /**
     * PHP type → JSON Schema type mapping.
     *
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'int' => 'integer',
        'float' => 'number',
        'bool' => 'boolean',
        'string' => 'string',
        'array' => 'array',
        'object' => 'object',
        'mixed' => 'string', // Fallback: treat mixed as string for schema safety
    ];

    /**
     * Generate a JSON Schema array from a Command class FQCN.
     *
     * @param  class-string  $className  Fully qualified class name of the Command.
     * @return array<string, mixed> JSON Schema as associative array.
     *
     * @throws \ReflectionException If the class does not exist.
     * @throws \InvalidArgumentException If the class has no public constructor properties.
     */
    public function generate(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            throw new \InvalidArgumentException("Class [{$className}] has no constructor.");
        }

        $properties = [];
        $required = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $schemaType = $this->resolveType($param->getType());

            // Nullable types: add "null" variant
            if ($param->getType() instanceof ReflectionNamedType && $param->getType()->allowsNull()) {
                $schemaType = $this->wrapNullable($schemaType);
            } elseif ($param->getType() instanceof ReflectionUnionType) {
                // Union types already handled in resolveType, check for null allowance
                foreach ($param->getType()->getTypes() as $unionType) {
                    if ($unionType instanceof ReflectionNamedType && $unionType->getName() === 'null') {
                        $schemaType = $this->wrapNullable($schemaType);
                        break;
                    }
                }
            }

            $property = ['type' => $schemaType];

            // Add description from docblock if available
            $docComment = $this->extractParamDoc($param->getName(), $constructor->getDocComment() ?: '');
            if ($docComment) {
                $property['description'] = $docComment;
            }

            // Mark default values
            if ($param->isDefaultValueAvailable()) {
                $property['default'] = $param->getDefaultValue();
            }

            $properties[$name] = $property;

            // Required: no default value AND not nullable
            if (! $param->isOptional()) {
                $required[] = $name;
            }
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => $reflection->getShortName(),
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * Convert a JSON Schema array to a JSON string.
     *
     * @param  array<string, mixed>  $schema
     */
    public function toJson(array $schema): string
    {
        return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Resolve a ReflectionType to a JSON Schema type string.
     */
    private function resolveType(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'string'; // Untyped → fallback to string
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            return self::TYPE_MAP[$name] ?? 'string';
        }

        if ($type instanceof ReflectionUnionType) {
            // For union types like int|string, pick the first non-null type
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() !== 'null') {
                    return self::TYPE_MAP[$unionType->getName()] ?? 'string';
                }
            }
        }

        return 'string';
    }

    /**
     * Wrap a type as nullable: ["string", "null"].
     */
    private function wrapNullable(string $type): array
    {
        return [$type, 'null'];
    }

    /**
     * Extract inline doc comment for a constructor parameter.
     */
    private function extractParamDoc(string $paramName, string $docComment): ?string
    {
        // Match @param or inline /** */ style comments
        if (preg_match('/@param\s+\S+\s+\$'.preg_quote((string) $paramName, '/').'\s+(.+)/i', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
