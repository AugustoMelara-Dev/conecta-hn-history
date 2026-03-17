<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Shared\Exceptions\SchemaViolationException;
use App\Infrastructure\Common\SchemaRegistry\SchemaNotFoundException;
use App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface;

final readonly class JsonSchemaValidatorService implements SchemaValidatorInterface
{
    public function __construct(
        private SchemaRegistryInterface $registry
    ) {}

    public function validate(string $subject, array $payload): void
    {
        $schemaData = $this->resolveSchema($subject);
        $requiredFields = $schemaData['required'] ?? [];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (! array_key_exists((string) $field, $payload)) {
                $missingFields[] = (string) $field;
            }
        }

        if ($missingFields !== []) {
            throw SchemaViolationException::forSubject(
                $subject,
                'Missing required field(s): '.implode(', ', $missingFields)
            );
        }
    }

    /**
     * The registry currently points to resources/schemas, while event schemas
     * may still live in namespaced subdirectories such as resources/schemas/events.
     *
     * @return array<string, mixed>
     */
    private function resolveSchema(string $subject): array
    {
        $candidates = [
            $subject,
            'events/'.$subject,
            'auth/'.$subject,
        ];

        foreach ($candidates as $candidate) {
            try {
                return $this->registry->getSchema($candidate);
            } catch (SchemaNotFoundException) {
                continue;
            }
        }

        throw new SchemaViolationException("Schema not found for subject [{$subject}]");
    }
}
