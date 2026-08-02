<?php

declare(strict_types=1);

namespace App\Application\Markets;

final readonly class HondurasMarketBackfillReport
{
    /**
     * @param  array<string, int>  $source
     * @param  array<string, int>  $mapped
     * @param  array<string, int>  $gaps
     */
    public function __construct(
        public array $source,
        public array $mapped,
        public array $gaps,
        public bool $officialAdministrativeDatasetComplete,
    ) {}

    /**
     * @return array{source: array<string, int>, mapped: array<string, int>, gaps: array<string, int>, official_administrative_dataset_complete: bool}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'mapped' => $this->mapped,
            'gaps' => $this->gaps,
            'official_administrative_dataset_complete' => $this->officialAdministrativeDatasetComplete,
        ];
    }
}
