<?php

declare(strict_types=1);

namespace App\Exports\Criteria;

use App\Exceptions\InvalidBusinessRuleException;
use App\Models\Order;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * SalesExportCriteria - Immutable Value Object for Export Filtering
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITERIA PATTERN: Type-Safe Export Filtering with DoS Prevention       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This VO encapsulates all export filters with built-in validation.      ║
 * ║  Prevents DoS attacks by:                                               ║
 * ║    1. Limiting date ranges to MAX 365 days                              ║
 * ║    2. ALWAYS applying a default date range (no unbounded queries)       ║
 * ║  TIMEZONE SAFE: Uses app timezone via now() helper                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SalesExportCriteria
{
    private const MAX_DATE_RANGE_DAYS = 365;
    private const DEFAULT_DATE_RANGE_DAYS = 30;

    public function __construct(
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
        private ?string $status = null,
    ) {
        $this->validateDateRange();
        $this->validateStatus();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Factories
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create criteria from string dates with optional status.
     * If dates are null, applies default safety range (last 30 days).
     *
     * TIMEZONE SAFE: Uses app timezone from config/app.php via now() helper.
     */
    public static function create(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null,
    ): self {
        // TIMEZONE FIX: Use now() which respects app.timezone config
        $appNow = now();

        // DoS PREVENTION: Never allow unbounded queries
        if ($startDate !== null) {
            $start = CarbonImmutable::parse($startDate, config('app.timezone'))->startOfDay();
        } else {
            $start = $appNow->copy()->subDays(self::DEFAULT_DATE_RANGE_DAYS)->startOfDay()->toImmutable();
        }

        if ($endDate !== null) {
            $end = CarbonImmutable::parse($endDate, config('app.timezone'))->endOfDay();
        } else {
            $end = $appNow->copy()->endOfDay()->toImmutable();
        }

        return new self(
            startDate: DateTimeImmutable::createFromInterface($start),
            endDate: DateTimeImmutable::createFromInterface($end),
            status: $status,
        );
    }

    /**
     * Create criteria for a specific number of days back from today.
     *
     * TIMEZONE SAFE: Uses app timezone via now() helper.
     */
    public static function forLastDays(int $days): self
    {
        if ($days <= 0 || $days > self::MAX_DATE_RANGE_DAYS) {
            throw InvalidBusinessRuleException::custom(
                "Date range must be between 1 and " . self::MAX_DATE_RANGE_DAYS . " days"
            );
        }

        // TIMEZONE FIX: Use now() which respects app.timezone
        $appNow = now();
        $start = $appNow->copy()->subDays($days)->startOfDay()->toImmutable();
        $end = $appNow->copy()->endOfDay()->toImmutable();

        return new self(
            startDate: DateTimeImmutable::createFromInterface($start),
            endDate: DateTimeImmutable::createFromInterface($end),
            status: null,
        );
    }

    /**
     * Create criteria with default safety range (30 days).
     *
     * NOTE: This is NOT truly "unbounded" - it applies a safe default
     * to prevent DoS attacks from exporting entire transaction history.
     */
    public static function withDefaultRange(): self
    {
        return self::forLastDays(self::DEFAULT_DATE_RANGE_DAYS);
    }

    /**
     * @deprecated Use withDefaultRange() instead. "Unbounded" concept is a DoS risk.
     */
    public static function unbounded(): self
    {
        trigger_error(
            'SalesExportCriteria::unbounded() is deprecated. Use withDefaultRange() instead.',
            E_USER_DEPRECATED
        );

        return self::withDefaultRange();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Getters (Strictly Typed)
    // ═══════════════════════════════════════════════════════════════════════

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function hasStatus(): bool
    {
        return $this->status !== null;
    }

    public function dateRangeInDays(): int
    {
        return $this->startDate->diff($this->endDate)->days;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Audit Hash (For Traceability)
    // ═══════════════════════════════════════════════════════════════════════

    public function toHash(): string
    {
        return hash('sha256', json_encode([
            'start' => $this->startDate->format('Y-m-d'),
            'end' => $this->endDate->format('Y-m-d'),
            'status' => $this->status,
        ]));
    }

    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'status' => $this->status,
            'range_days' => $this->dateRangeInDays(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Validation (Invariants)
    // ═══════════════════════════════════════════════════════════════════════

    private function validateDateRange(): void
    {
        // Start must be before End
        if ($this->startDate > $this->endDate) {
            throw InvalidBusinessRuleException::custom(
                'Start date must be before or equal to end date'
            );
        }

        // Limit range to prevent DoS
        $diff = $this->startDate->diff($this->endDate);
        if ($diff->days > self::MAX_DATE_RANGE_DAYS) {
            throw InvalidBusinessRuleException::custom(
                "Date range cannot exceed " . self::MAX_DATE_RANGE_DAYS . " days to prevent system overload"
            );
        }
    }

    private function validateStatus(): void
    {
        if ($this->status === null) {
            return;
        }

        // Use Order::STATUSES as single source of truth
        if (!in_array($this->status, Order::STATUSES, true)) {
            throw InvalidBusinessRuleException::custom(
                "Invalid status: {$this->status}. Valid: " . implode(', ', Order::STATUSES)
            );
        }
    }
}
