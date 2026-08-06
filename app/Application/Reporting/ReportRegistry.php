<?php

declare(strict_types=1);

namespace App\Application\Reporting;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Domain\Reporting\Enums\ReportKey;
use InvalidArgumentException;

/**
 * ReportRegistry - Mapea ReportKey ? ReportInterface.
 *
 * Singleton service. Cada Report class se registra al boot.
 */
class ReportRegistry
{
    /** @var array<string, ReportInterface> */
    private array $reports = [];

    public function register(ReportInterface $report): void
    {
        $this->reports[$report->getKey()->value] = $report;
    }

    public function get(ReportKey $key): ReportInterface
    {
        if (! isset($this->reports[$key->value])) {
            throw new InvalidArgumentException("Report not registered: {$key->value}");
        }
        return $this->reports[$key->value];
    }

    public function has(ReportKey $key): bool
    {
        return isset($this->reports[$key->value]);
    }

    /**
     * @return array<int, ReportInterface>
     */
    public function all(): array
    {
        return array_values($this->reports);
    }

    /**
     * @return array<int, ReportInterface>
     */
    public function byCategory(string $category): array
    {
        return array_values(array_filter(
            $this->reports,
            fn (ReportInterface $r) => $r->getKey()->category() === $category,
        ));
    }

    /**
     * @return array<string, array<int, ReportInterface>>
     */
    public function grouped(): array
    {
        $grouped = [];
        foreach ($this->reports as $report) {
            $cat = $report->getKey()->category();
            $grouped[$cat][] = $report;
        }
        return $grouped;
    }
}
