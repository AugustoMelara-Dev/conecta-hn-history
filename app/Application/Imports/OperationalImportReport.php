<?php

declare(strict_types=1);

namespace App\Application\Imports;

final readonly class OperationalImportReport
{
    public function __construct(
        public string $batchId,
        public int $total,
        public int $approved,
        public int $rejected,
        public int $failed,
        public int $published,
    ) {}
}
