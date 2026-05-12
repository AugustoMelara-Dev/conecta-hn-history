<?php

declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase as BaseTestCase;

/**
 * ContractTestCase
 *
 * Base class for Contract Testing.
 * Enforces JSON Schema compliance for API responses/View Models.
 */
abstract class ContractTestCase extends BaseTestCase
{
    /**
     * Assert that the given data matches the defined JSON Schema.
     *
     * @param  string  $subject  Logical schema name (e.g. 'user_profile')
     * @param  array  $responseData  The array representation of the object to validate
     */
    protected function assertMatchesJsonSchema(string $subject, array $responseData): void
    {
        // 1. REGLA DE ABSTRACCIÓN: Usar el Registry, no el file system.
        $registry = $this->app->make(\App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface::class);

        $schema = $registry->getSchema($subject);

        $this->validateDataAgainstSchema($responseData, $schema, 'root');
    }

    /**
     * Recursive validation of data against schema constraints.
     */
    private function validateDataAgainstSchema(mixed $data, array $schema, string $path): void
    {
        // 1. Type Validation (Basic support for string, integer, boolean, object/array)
        if (isset($schema['type'])) {
            $expectedType = $schema['type'];

            // Handle nullable types (e.g. ["string", "null"])
            if (is_array($expectedType)) {
                if (is_null($data) && in_array('null', $expectedType)) {
                    return; // Valid null
                }
                // Filter out 'null' to check the actual type
                $types = array_filter($expectedType, fn ($t) => $t !== 'null');
                $expectedType = reset($types);
            }

            if ($expectedType === 'string' && ! is_string($data)) {
                $this->fail("Property [{$path}] expected string, got ".gettype($data));
            }
            if ($expectedType === 'integer' && ! is_int($data)) {
                $this->fail("Property [{$path}] expected integer, got ".gettype($data));
            }
            if ($expectedType === 'boolean' && ! is_bool($data)) {
                $this->fail("Property [{$path}] expected boolean, got ".gettype($data));
            }
        }

        // 2. Required Properties Validation
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (! array_key_exists($requiredField, $data)) {
                    $this->fail("Missing required property [{$path}.{$requiredField}] in contract.");
                }
            }
        }

        // 3. Properties Recursion
        if (isset($schema['properties']) && is_array($data)) {
            foreach ($schema['properties'] as $field => $fieldSchema) {
                if (array_key_exists($field, $data)) {
                    $this->validateDataAgainstSchema($data[$field], $fieldSchema, "{$path}.{$field}");
                }
            }
        }
    }
}
