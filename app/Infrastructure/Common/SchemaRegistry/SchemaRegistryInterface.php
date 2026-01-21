<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\SchemaRegistry;

interface SchemaRegistryInterface
{
    /**
     * Retrieve the JSON Schema for a given subject.
     *
     * @param  string  $subject  The logical name of the schema (e.g., 'user_profile')
     * @return array<string, mixed> The decoded schema
     *
     * @throws SchemaNotFoundException If the schema cannot be located
     */
    public function getSchema(string $subject): array;
}
