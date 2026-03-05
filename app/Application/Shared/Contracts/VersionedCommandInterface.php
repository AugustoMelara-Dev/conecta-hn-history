<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface VersionedCommandInterface
{
    public function getSchemaVersion(): int;
}
