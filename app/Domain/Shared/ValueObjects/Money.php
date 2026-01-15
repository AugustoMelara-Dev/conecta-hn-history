<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Money - Immutable Value Object for Financial Precision
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  BANKING GRADE: Encapsulates amount + currency as indivisible unit.     ║
 * ║  IMMUTABLE: All operations return new instances.                         ║
 * ║  TYPE SAFE: Prevents cross-currency arithmetic errors.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class Money
{
    private const VALID_CURRENCIES = ['USD', 'HNL', 'EUR', 'MXN', 'GTQ'];

    public function __construct(
        public int $amount,
        public string $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException("Money amount cannot be negative: {$amount}");
        }

        if (!in_array($currency, self::VALID_CURRENCIES, true)) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code: {$currency}");
        }
    }

    /**
     * Factory: Create USD Money.
     */
    public static function USD(int $cents): self
    {
        return new self($cents, 'USD');
    }

    /**
     * Factory: Create HNL Money.
     */
    public static function HNL(int $centavos): self
    {
        return new self($centavos, 'HNL');
    }

    /**
     * Factory: Create from any supported currency.
     */
    public static function of(int $amount, string $currency): self
    {
        return new self($amount, strtoupper($currency));
    }

    /**
     * Check equality (amount AND currency must match).
     */
    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    /**
     * Add two Money objects (must be same currency).
     *
     * @throws InvalidArgumentException if currencies differ
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Format for display (e.g., "$19.99").
     */
    public function format(): string
    {
        $major = intdiv($this->amount, 100);
        $minor = abs($this->amount % 100);
        $symbol = match ($this->currency) {
            'USD' => '$',
            'HNL' => 'L',
            'EUR' => '€',
            'MXN' => '$',
            'GTQ' => 'Q',
            default => $this->currency . ' ',
        };

        return sprintf('%s%d.%02d', $symbol, $major, $minor);
    }

    /**
     * Serialize to array for transport/storage.
     *
     * @return array{amount: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
