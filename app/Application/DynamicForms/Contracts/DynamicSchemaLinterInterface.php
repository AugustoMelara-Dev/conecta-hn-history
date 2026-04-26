<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

interface DynamicSchemaLinterInterface
{
    /**
     * @return array{errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function lint(bool $strict = false, bool $changedOnly = false): array;
}

