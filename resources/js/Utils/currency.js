/**
 * currency.js - Centralized Currency Formatting Utility
 *
 * ARCHITECTURE:
 * - Single source of truth for currency formatting
 * - Defensive null/undefined handling
 * - Configured for Honduran Lempiras (HNL)
 *
 * @module Utils/currency
 */

/**
 * Format a numeric amount to Honduran Lempiras
 *
 * @param {number|null|undefined} amount - The amount to format
 * @returns {string} Formatted currency string (e.g., "L 150")
 *
 * @example
 * formatCurrency(150.5) // "L 151"
 * formatCurrency(null)  // "L 0"
 * formatCurrency()      // "L 0"
 */
export function formatCurrency(amount) {
    // Defensive: Handle null, undefined, NaN
    const safeAmount = Number(amount) || 0;

    return new Intl.NumberFormat('es-HN', {
        style: 'currency',
        currency: 'HNL',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(safeAmount);
}

/**
 * Format currency with decimals (for precise display)
 *
 * @param {number|null|undefined} amount - The amount to format
 * @returns {string} Formatted currency with 2 decimal places
 */
export function formatCurrencyPrecise(amount) {
    const safeAmount = Number(amount) || 0;

    return new Intl.NumberFormat('es-HN', {
        style: 'currency',
        currency: 'HNL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(safeAmount);
}

export default formatCurrency;
