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
        $versionedSnakeSubject = $subject;
        $segments = explode('.', $subject);
        if (count($segments) >= 2) {
            $version = array_pop($segments);
            $versionedSnakeSubject = implode('_', $segments).'.'.$version;
        }

        $paths = [
            $this->basePath.DIRECTORY_SEPARATOR.$subject.'.json',
            $this->basePath.DIRECTORY_SEPARATOR.$subject.'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.$subject.'.json',
            $this->basePath.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.$subject.'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.$subject.'.json',
            $this->basePath.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.$subject.'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.str_replace('.', '_', $subject).'.json',
            $this->basePath.DIRECTORY_SEPARATOR.str_replace('.', '_', $subject).'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.str_replace('.', '_', $subject).'.json',
            $this->basePath.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.str_replace('.', '_', $subject).'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.str_replace('.', '_', $subject).'.json',
            $this->basePath.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.str_replace('.', '_', $subject).'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.$versionedSnakeSubject.'.json',
            $this->basePath.DIRECTORY_SEPARATOR.$versionedSnakeSubject.'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.$versionedSnakeSubject.'.json',
            $this->basePath.DIRECTORY_SEPARATOR.'events'.DIRECTORY_SEPARATOR.$versionedSnakeSubject.'.schema.json',
            $this->basePath.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.$versionedSnakeSubject.'.json',
            $this->basePath.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.$versionedSnakeSubject.'.schema.json',
        ];
        $path = null;
        foreach ($paths as $candidate) {
            if (file_exists($candidate)) {
                $path = $candidate;
                break;
            }
        }
        if ($path === null) {
            throw new SchemaNotFoundException("Schema not found for subject [{$subject}]");
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
