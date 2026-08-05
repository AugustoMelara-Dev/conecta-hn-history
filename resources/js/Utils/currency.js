/**
 * currency.js - Centralized Currency Formatting Utility
 *
 * ARCHITECTURE:
 * - Single source of truth for currency formatting
 * - Defensive null/undefined handling
 * - Locale and ISO 4217 currency are explicit inputs
 *
 * @module Utils/currency
 */

/**
 * Format a numeric amount using an explicit market context.
 *
 * @param {number|null|undefined} amount - The amount to format
 * @param {{locale: string, currency: string}} market
 * @returns {string} Formatted currency string
 *
 * @example
 * formatCurrency(150.5, { locale: 'en-CA', currency: 'CAD' })
 */
export function formatCurrency(amount, market) {
    const { locale, currency } = requireMarket(market);
    // Defensive: Handle null, undefined, NaN
    const safeAmount = Number(amount) || 0;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(safeAmount);
}

/**
 * Format currency with decimals (for precise display)
 *
 * @param {number|null|undefined} amount - The amount to format
 * @param {{locale: string, currency: string}} market
 * @returns {string} Formatted currency with 2 decimal places
 */
export function formatCurrencyPrecise(amount, market) {
    const { locale, currency } = requireMarket(market);
    const safeAmount = Number(amount) || 0;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(safeAmount);
}

function requireMarket(market) {
    if (!market?.locale || !market?.currency) {
        throw new TypeError('Currency formatting requires explicit locale and currency.');
    }

    return {
        locale: String(market.locale),
        currency: String(market.currency).toUpperCase(),
    };
}

export default formatCurrency;
