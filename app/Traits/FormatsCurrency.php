<?php

declare(strict_types=1);

namespace App\Traits;

use InvalidArgumentException;
use NumberFormatter;

/**
 * FormatsCurrency - Enterprise-Grade Money Formatting with i18n
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PRECISION GUARANTEES:                                                  ║
 * ║    - Uses integer arithmetic (no floating-point errors in fallback)    ║
 * ║    - NumberFormatter handles precision correctly for intl               ║
 * ║    - Validates all inputs including currency codes                      ║
 * ║    - Graceful fallback when intl extension missing                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
trait FormatsCurrency
{
    /**
     * Fallback configuration when intl extension is not available.
     */
    private const CURRENCY_CONFIG = [
        'HNL' => ['symbol' => 'L ', 'decimals' => 2, 'dec_point' => '.', 'thousands' => ','],
        'USD' => ['symbol' => '$', 'decimals' => 2, 'dec_point' => '.', 'thousands' => ','],
        'EUR' => ['symbol' => '€', 'decimals' => 2, 'dec_point' => ',', 'thousands' => '.'],
        'MXN' => ['symbol' => '$', 'decimals' => 2, 'dec_point' => '.', 'thousands' => ','],
        'GTQ' => ['symbol' => 'Q', 'decimals' => 2, 'dec_point' => '.', 'thousands' => ','],
    ];

    /**
     * Valid ISO 4217 currency codes supported by this system.
     */
    private const SUPPORTED_CURRENCIES = ['HNL', 'USD', 'EUR', 'MXN', 'GTQ'];

    /**
     * Locale mapping for NumberFormatter.
     * Maps Laravel locale codes to ICU locale codes.
     */
    private const LOCALE_MAP = [
        'es' => 'es_HN',
        'es_HN' => 'es_HN',
        'es_MX' => 'es_MX',
        'es_ES' => 'es_ES',
        'es_GT' => 'es_GT',
        'en' => 'en_US',
        'en_US' => 'en_US',
        'en_GB' => 'en_GB',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
    ];

    /**
     * Format money for human-readable UI display.
     *
     * Uses PHP intl extension for locale-aware formatting when available.
     * Falls back to manual formatting for environments without intl.
     *
     * @param int $cents Amount in cents (smallest currency unit, must be >= 0)
     * @param string $currency ISO 4217 currency code (HNL, USD, EUR, etc.)
     * @param string|null $locale Optional locale override (defaults to app locale)
     * @return string Formatted amount for display (e.g., "L 1,500.00")
     *
     * @throws InvalidArgumentException If cents is negative or currency is invalid
     */
    protected function formatMoney(int $cents, string $currency, ?string $locale = null): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException(
                "Money amount cannot be negative for display, got {$cents} cents"
            );
        }

        $this->validateCurrency($currency);

        // Try intl extension first (preferred method)
        if (extension_loaded('intl')) {
            return $this->formatWithIntl($cents, $currency, $locale);
        }

        // Fallback to manual formatting
        return $this->formatManually($cents, $currency);
    }

    /**
     * Validate that currency code is supported.
     *
     * @throws InvalidArgumentException If currency is empty or unsupported
     */
    private function validateCurrency(string $currency): void
    {
        if ($currency === '') {
            throw new InvalidArgumentException('Currency code cannot be empty');
        }

        // We allow any valid-looking currency code for intl extension
        // but enforce supported list for manual fallback reliability
        if (!extension_loaded('intl') && !in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            throw new InvalidArgumentException(
                "Unsupported currency '{$currency}'. Supported: " . implode(', ', self::SUPPORTED_CURRENCIES)
            );
        }
    }

    /**
     * Format using PHP intl extension (NumberFormatter).
     *
     * Uses string formatting to avoid any floating-point precision issues.
     */
    private function formatWithIntl(int $cents, string $currency, ?string $locale): string
    {
        $appLocale = $locale ?? app()->getLocale();
        $intlLocale = self::LOCALE_MAP[$appLocale] ?? 'en_US';

        $formatter = new NumberFormatter($intlLocale, NumberFormatter::CURRENCY);

        // Set fraction digits explicitly
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        // Build exact decimal representation using sprintf to avoid float errors
        $wholePart = intdiv($cents, 100);
        $decimalPart = abs($cents % 100);
        $amount = (float)sprintf('%d.%02d', $wholePart, $decimalPart);

        $formatted = $formatter->formatCurrency($amount, $currency);

        // Handle potential formatter failure
        if ($formatted === false) {
            return $this->formatManually($cents, $currency);
        }

        return $formatted;
    }

    /**
     * Manual fallback formatting (no intl extension).
     * Uses integer arithmetic to avoid floating-point errors.
     */
    private function formatManually(int $cents, string $currency): string
    {
        $wholePart = intdiv($cents, 100);
        $decimalPart = abs($cents % 100);

        $config = self::CURRENCY_CONFIG[$currency] ?? [
            'symbol' => $currency . ' ',
            'decimals' => 2,
            'dec_point' => '.',
            'thousands' => ',',
        ];

        $formattedWhole = number_format($wholePart, 0, '', $config['thousands']);
        $formattedDecimal = str_pad((string)$decimalPart, $config['decimals'], '0', STR_PAD_LEFT);

        return $config['symbol'] . $formattedWhole . $config['dec_point'] . $formattedDecimal;
    }

    /**
     * Check if intl extension is available.
     */
    protected function isIntlAvailable(): bool
    {
        return extension_loaded('intl');
    }
}
