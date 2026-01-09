<?php

declare(strict_types=1);

namespace App\Exports\Concerns;

/**
 * SanitizesOutput - Intelligent CSV Injection Protection Trait
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CSV INJECTION ATTACK (CVE-2014-3524)                                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  THREAT: Attackers inject Excel formulas into exported data.            ║
 * ║          When users open CSV/XLSX in Excel, formulas execute.           ║
 * ║                                                                          ║
 * ║  ATTACK EXAMPLE:                                                        ║
 * ║    Customer Name: "=CMD|'/C calc'!A1"                                   ║
 * ║    → Opens calculator on user's machine (code execution)                ║
 * ║                                                                          ║
 * ║  INTELLIGENT MITIGATION:                                                ║
 * ║    - Escape cells starting with: = + - @ TAB CR LF                      ║
 * ║    - BUT: Preserve legitimate negative numbers (-100.50)                ║
 * ║    - Detect formulas by checking for non-numeric content               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
trait SanitizesOutput
{
    /**
     * Characters that trigger formula execution in Excel.
     */
    private const DANGEROUS_PREFIXES = ['=', '@', "\t", "\r", "\n"];

    /**
     * Prefixes that MIGHT be dangerous, but could also be valid numbers.
     */
    private const NUMERIC_PREFIXES = ['+', '-'];

    /**
     * Sanitize a string value for safe Excel/CSV export.
     *
     * INTELLIGENT LOGIC:
     * - Always escape: = @ TAB CR LF
     * - For + and -: Only escape if the value is NOT a valid number
     *
     * @param  mixed  $value  The value to sanitize
     * @return mixed The sanitized value (strings are escaped, others pass through)
     */
    protected function sanitize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return $value;
        }

        // Check for definitely dangerous prefixes (always escape)
        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        // Check for numeric prefixes (+ and -)
        foreach (self::NUMERIC_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                // INTELLIGENT CHECK: Is this a legitimate number?
                if ($this->isLegitimateNumber($value)) {
                    // Don't escape valid numbers like -150.00 or +42
                    return $value;
                }

                // It's a formula or command, escape it
                return "'".$value;
            }
        }

        return $value;
    }

    /**
     * Check if a string is a legitimate numeric value.
     *
     * Handles:
     * - Standard numbers: -150, +42, 100.50
     * - Scientific notation: -1.5e10, +2.5E-3
     * - Currency formatted: -$150.00 (after stripping $)
     */
    private function isLegitimateNumber(string $value): bool
    {
        // Remove currency symbols for checking
        $cleaned = preg_replace('/[$€£L\s,]/', '', $value);

        // Check if it's numeric after cleaning
        return is_numeric($cleaned);
    }

    /**
     * Sanitize an entire array of values.
     *
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    protected function sanitizeAll(array $values): array
    {
        return array_map(fn ($v) => $this->sanitize($v), $values);
    }

    /**
     * Check if a value contains a potential CSV injection attempt.
     * Use for logging/alerting purposes.
     *
     * @return bool Returns true if this looks like an attack attempt
     */
    protected function detectsInjectionAttempt(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Check for definitely dangerous prefixes
        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        // Check for numeric prefixes that aren't actually numbers
        foreach (self::NUMERIC_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix) && ! $this->isLegitimateNumber($value)) {
                // Additional heuristic: formulas often contain pipes, exclamation marks, or parentheses
                if (preg_match('/[\|!()]/', $value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
