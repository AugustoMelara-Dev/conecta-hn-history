<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

use RuntimeException;

final class AiHandoffNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('AI handoff was not found.');
    }
}
