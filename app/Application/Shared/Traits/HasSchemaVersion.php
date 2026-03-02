<?php

declare(strict_types=1);

namespace App\Application\Shared\Traits;

trait HasSchemaVersion
{
    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
