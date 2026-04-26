<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

interface DynamicConfigAuditInterface
{
    public function record(object $event): void;
}

