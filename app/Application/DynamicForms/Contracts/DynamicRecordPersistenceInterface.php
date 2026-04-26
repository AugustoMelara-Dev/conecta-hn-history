<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

use App\Models\Dynamic\DynamicRecord;
use App\Models\Dynamic\EntityDefinition;
use Illuminate\Validation\ValidationException;

interface DynamicRecordPersistenceInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $coreAttributes
     *
     * @throws ValidationException
     */
    public function upsert(
        EntityDefinition $entityDefinition,
        array $payload,
        array $coreAttributes = [],
        ?DynamicRecord $record = null
    ): DynamicRecord;
}

