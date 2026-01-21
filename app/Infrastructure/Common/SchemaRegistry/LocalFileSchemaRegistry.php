<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\SchemaRegistry;

use RuntimeException;

class SchemaNotFoundException extends RuntimeException {}

final readonly class LocalFileSchemaRegistry implements SchemaRegistryInterface
{
    public function __construct(
        private string $basePath
    ) {}

    public function getSchema(string $subject): array
    {
        // Safe Path Construction
        $path = $this->basePath.DIRECTORY_SEPARATOR.$subject.'.json';

        if (! file_exists($path)) {
            throw new SchemaNotFoundException("Schema not found for subject [{$subject}] at path: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read schema file: {$path}");
        }

        $schema = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in schema file [{$subject}]: ".json_last_error_msg());
        }

        return $schema;
    }
}
