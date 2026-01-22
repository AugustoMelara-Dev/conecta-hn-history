<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Shared\Exceptions\SchemaViolationException;
use App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final readonly class JsonSchemaValidatorService implements SchemaValidatorInterface
{
    private Validator $validator;

    public function __construct(
        private SchemaRegistryInterface $registry
    ) {
        $this->validator = new \Opis\JsonSchema\Validator;

        // Optimize: register a loader if needed, but for now we fetch manually via registry
        // To strictly use Opis with our Registry, we might need a custom loader adapter.
        // For simplicity in this iteration, we will load the schema array and pass it to Opis.
        // Opis accepts decoded JSON (stdClass/array) as schema.
    }

    public function validate(string $subject, array $payload): void
    {
        // 1. Fetch Schema from Registry
        $schemaData = $this->registry->getSchema($subject);

        // 2. Encode/Decode to ensure we have stdClass/array structure Opis expects
        // (Opis generally works with json_decode'd objects)
        $data = json_decode(json_encode($payload));
        $schema = json_decode(json_encode($schemaData));

        // 3. Validate
        $result = $this->validator->validate($data, $schema);

        if (! $result->isValid()) {
            $formatter = new ErrorFormatter;
            $errors = $formatter->format($result->error()); // Generic formatting

            // Flatten errors for log/exception
            $errorMsg = json_encode($errors);

            throw SchemaViolationException::forSubject($subject, $errorMsg);
        }
    }
}
