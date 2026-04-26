<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\SchemaVersion;
use App\Models\User;

interface DynamicSchemaRegistryInterface
{
    public function resolveEntity(string $entityKey, ?string $tenantId = null): EntityDefinition;

    public function getActiveSchemaData(EntityDefinition $entity): DynamicSchemaData;

    public function buildLiveSchemaData(EntityDefinition $entity): DynamicSchemaData;

    public function publish(EntityDefinition $entity, ?User $publisher = null): SchemaVersion;
}

