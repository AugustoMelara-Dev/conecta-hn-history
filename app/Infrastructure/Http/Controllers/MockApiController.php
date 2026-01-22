<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface;
use Faker\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MockApiController extends Controller
{
    private $faker;

    public function __construct(
        private SchemaRegistryInterface $schemaRegistry
    ) {
        $this->faker = Factory::create();
    }

    public function handle(string $schemaKey): JsonResponse
    {
        try {
            $schema = $this->schemaRegistry->getSchema($schemaKey);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Schema not found'], 404);
        }

        $data = $this->generateFakeData($schema);

        return response()->json($data);
    }

    private function generateFakeData(array $schema): mixed
    {
        $type = $schema['type'] ?? 'string';

        // Handle Nullable
        if (is_array($type)) {
            // 20% chance of null if nullable
            if (in_array('null', $type) && $this->faker->boolean(20)) {
                return null;
            }
            // Fallback to first non-null type
            $types = array_values(array_filter($type, fn ($t) => $t !== 'null'));
            $type = $types[0] ?? 'string';
        }

        return match ($type) {
            'object' => $this->fakeObject($schema),
            'array' => $this->fakeArray($schema),
            'integer' => $this->faker->numberBetween(1, 1000),
            'number' => $this->faker->randomFloat(2, 10, 1000),
            'boolean' => $this->faker->boolean(),
            'string' => $this->fakeString($schema),
            default => null,
        };
    }

    private function fakeObject(array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        $result = [];

        foreach ($properties as $key => $config) {
            $result[$key] = $this->generateFakeData($config);
        }

        return $result;
    }

    private function fakeArray(array $schema): array
    {
        $itemSchema = $schema['items'] ?? ['type' => 'string'];
        $count = $this->faker->numberBetween(1, 4);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->generateFakeData($itemSchema);
        }

        return $result;
    }

    private function fakeString(array $schema): string
    {
        // Heuristic Inference based on field context if available
        // Note: In a real recursion, we might pass the field name.
        // Here we rely on basic Faker fallback or generic strings.

        // Ideally we would pass the key name down.
        // For Titanium Standard, let's keep it simple but functional.

        if (isset($schema['format'])) {
            if ($schema['format'] === 'email') {
                return $this->faker->email();
            }
            if ($schema['format'] === 'date-time') {
                return $this->faker->iso8601();
            }
            if ($schema['format'] === 'uuid') {
                return $this->faker->uuid();
            }
        }

        return $this->faker->word();
    }
}
