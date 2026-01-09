<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Money Value Object - Immutable Financial Data Container
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Why Integer-Based Money Representation?        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Floating-Point Precision Errors                                ║
 * ║    0.1 + 0.2 = 0.30000000000000004 (IEEE 754 standard)                  ║
 * ║    This causes $100.00 + $0.10 = $100.10000000000001                    ║
 * ║    Over millions of transactions: significant financial discrepancies   ║
 * ║                                                                          ║
 * ║  SOLUTION: Store ALL monetary values as integers (cents/centavos)        ║
 * ║    L. 100.50 HNL → 10050 (integer in database)                          ║
 * ║    Integer math is always exact: 10000 + 10 = 10010 (guaranteed)        ║
 * ║                                                                          ║
 * ║  IMMUTABILITY: Once created, the Money object cannot be modified.        ║
 * ║    All operations return NEW Money instances.                           ║
 * ║    This prevents "spooky action at a distance" bugs where a shared      ║
 * ║    reference is mutated unexpectedly.                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   $price = Money::fromCents(10050, 'HNL');
 *   $price->formatted(); // "L. 100.50"
 *   $total = $price->add(Money::fromCents(500, 'HNL')); // New Money object
 */
final readonly class Money implements \JsonSerializable
{
    /**
     * @param  int  $cents  The monetary amount in smallest currency unit (centavos)
     * @param  string  $currency  ISO 4217 currency code (default: HNL for Honduras)
     */
    public function __construct(
        private int $cents,
        private string $currency = 'HNL'
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }
    }

    /**
     * Create Money from cents (DATABASE → DOMAIN).
     */
    public static function fromCents(int $cents, string $currency = 'HNL'): self
    {
        return new self($cents, $currency);
    }

    /**
     * Create Money from decimal amount (USER INPUT → DOMAIN).
     *
     * WARNING: Use only for user input! Internal operations should use cents.
     */
    public static function fromDecimal(float|string $amount, string $currency = 'HNL'): self
    {
        // Convert to string to avoid float precision issues during conversion
        $cents = (int) round((float) $amount * 100);

        return new self($cents, $currency);
    }

    /**
     * Create a zero-value Money object.
     */
    public static function zero(string $currency = 'HNL'): self
    {
        return new self(0, $currency);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACCESSORS (Read-Only Properties)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the amount in cents (for database storage).
     */
    public function cents(): int
    {
        return $this->cents;
    }

    /**
     * Get the currency code.
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Get the decimal representation (for display purposes only).
     */
    public function toDecimal(): float
    {
        return $this->cents / 100;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ARITHMETIC OPERATIONS (Return NEW Money instances)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Add another Money amount.
     *
     * @throws InvalidArgumentException if currencies don't match
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    /**
     * Subtract another Money amount.
     *
     * @throws InvalidArgumentException if currencies don't match or result is negative
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        $result = $this->cents - $other->cents;

        if ($result < 0) {
            throw new InvalidArgumentException('Subtraction would result in negative amount');
        }

        return new self($result, $this->currency);
    }

    /**
     * Multiply by an integer factor (for quantity calculations).
     */
    public function multiply(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }

    /**
     * Multiply by a percentage/decimal factor (for tax/discount calculations).
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  CRITICAL: Use this for percentages, NOT multiply()!                  ║
     * ║                                                                        ║
     * ║  WRONG:  $subtotal->multiply($taxRate / 100)  // 15/100=0.15→0 (int!) ║
     * ║  RIGHT:  $subtotal->multiplyByPercentage(15)  // Correct calculation  ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     *
     * @param  float  $percentage  The percentage (e.g., 15 for 15%)
     */
    public function multiplyByPercentage(float $percentage): self
    {
        $result = (int) round($this->cents * ($percentage / 100));

        return new self($result, $this->currency);
    }

    /**
     * Multiply by a decimal factor (for arbitrary multiplications).
     *
     * @param  float  $factor  The multiplication factor (e.g., 0.15 for 15%)
     */
    public function multiplyByFloat(float $factor): self
    {
        $result = (int) round($this->cents * $factor);

        return new self($result, $this->currency);
    }

    /**
     * Apply a percentage discount.
     *
     * @param  int  $percentage  Discount percentage (0-100)
     */
    public function discount(int $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100');
        }

        $discountAmount = (int) round($this->cents * ($percentage / 100));

        return new self($this->cents - $discountAmount, $this->currency);
    }

    /**
     * Allocate money across multiple recipients (e.g., payment splitting).
     *
     * Uses the Largest Remainder Method to distribute cents without losing money.
     *
     * @param  array<int>  $ratios  Allocation ratios [50, 30, 20] for 50%, 30%, 20%
     * @return array<Money>
     */
    public function allocate(array $ratios): array
    {
        $total = array_sum($ratios);
        $remainder = $this->cents;
        $shares = [];
        $results = [];

        // First pass: calculate integer shares
        foreach ($ratios as $ratio) {
            $share = (int) floor($this->cents * $ratio / $total);
            $shares[] = $share;
            $remainder -= $share;
        }

        // Second pass: distribute remainder to largest fractional parts
        $fractions = [];
        foreach ($ratios as $i => $ratio) {
            $fractions[$i] = ($this->cents * $ratio / $total) - $shares[$i];
        }
        arsort($fractions);

        foreach (array_keys($fractions) as $i) {
            if ($remainder <= 0) {
                break;
            }
            $shares[$i]++;
            $remainder--;
        }

        // Convert to Money objects
        foreach ($shares as $share) {
            $results[] = new self($share, $this->currency);
        }

        return $results;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPARISON OPERATIONS
    // ═══════════════════════════════════════════════════════════════════════

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents < $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FORMATTING (Display Layer)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Format for display with currency symbol.
     */
    public function formatted(): string
    {
        $symbol = match ($this->currency) {
            'HNL' => 'L.',
            'USD' => '$',
            'EUR' => '€',
            default => $this->currency.' ',
        };

        return $symbol.' '.number_format($this->toDecimal(), 2, '.', ',');
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        return $this->formatted();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on Money with different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JsonSerializable Implementation (Queue-Safe)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Serialize to JSON-safe array.
     * CRITICAL: cents must remain int, not float, for precision safety.
     */
    public function jsonSerialize(): array
    {
        return [
            'cents' => $this->cents,
            'currency' => $this->currency,
        ];
    }

    /**
     * Reconstruct from serialized array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['cents'],
            $data['currency'] ?? 'HNL'
        );
    }

    /**
     * Alias for cents() - for interface consistency with event serialization.
     *
     * @deprecated Use cents() instead for clarity. This method will be removed in v2.0.
     *             - cents() returns int (raw value in smallest currency unit)
     *             - toDecimal() returns float (for display purposes)
     */
    public function amount(): int
    {
        return $this->cents;
    }
}
