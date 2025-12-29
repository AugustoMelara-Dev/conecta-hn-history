/**
 * validators.js - Centralized Domain Validation Logic
 *
 * ARCHITECTURE:
 * This module centralizes all Honduras-specific validation rules.
 * When national regulations change (e.g., new phone prefixes),
 * update this single file to propagate changes globally.
 *
 * EXPORTS:
 * - HONDURAS_PHONE_REGEX: Pattern for mobile validation
 * - validateHondurasPhone(): Full validation with result object
 * - formatHondurasPhone(): Normalize phone format
 *
 * COVERAGE:
 * - Mobile prefixes: 3, 8, 9 (Tigo, Claro, Hondutel móvil)
 * - Landline prefixes: 2 (NOT accepted for WhatsApp)
 *
 * @module Utils/validators
 */

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * HONDURAS PHONE REGEX - CANONICAL SOURCE OF TRUTH
 *
 * Pattern: /^(\+?504[-\s]?)?[389]\d{3}[-\s]?\d{4}$/
 *
 * Breakdown:
 * - ^(\+?504[-\s]?)? : Optional country code 504 or +504 with optional dash/space
 * - [389]            : Must start with 3, 8, or 9 (mobile prefixes)
 * - \d{3}            : Three digits
 * - [-\s]?           : Optional dash or space separator
 * - \d{4}$           : Four digits at the end
 *
 * Valid examples:
 * - "9999-1234", "88881234", "+504 3111-2222"
 * - "504-9123-4567", "+504-8765-4321", "3456 7890"
 *
 * Invalid (landlines start with 2):
 * - "2222-1234", "22221234", "+504 2234-5678"
 * ═══════════════════════════════════════════════════════════════════════════
 */
export const HONDURAS_PHONE_REGEX = /^(\+?504[-\s]?)?[389]\d{3}[-\s]?\d{4}$/;

/**
 * Validate a Honduras mobile phone number
 *
 * @param {string} phone - The phone number to validate
 * @returns {Object} Validation result
 * @returns {boolean} result.isValid - Whether the phone is valid
 * @returns {string|null} result.error - Error message if invalid
 * @returns {string|null} result.normalized - Normalized phone (digits only) if valid
 *
 * @example
 * validateHondurasPhone('9999-1234')
 * // { isValid: true, error: null, normalized: '99991234' }
 *
 * validateHondurasPhone('2222-1234')
 * // { isValid: false, error: 'Debe ser celular hondureño (inicia con 3, 8 o 9)', normalized: null }
 */
export function validateHondurasPhone(phone) {
    // Guard: Empty input
    if (!phone || typeof phone !== 'string') {
        return {
            isValid: false,
            error: 'Número de teléfono requerido',
            normalized: null,
        };
    }

    const cleaned = phone.trim();

    // Guard: Minimum length
    if (cleaned.length < 8) {
        return {
            isValid: false,
            error: 'Mínimo 8 dígitos',
            normalized: null,
        };
    }

    // Apply regex
    if (!HONDURAS_PHONE_REGEX.test(cleaned)) {
        // Check if it's a landline (starts with 2)
        const digitsOnly = cleaned.replace(/\D/g, '');
        const significantDigits = digitsOnly.startsWith('504')
            ? digitsOnly.slice(3)
            : digitsOnly;

        if (significantDigits.startsWith('2')) {
            return {
                isValid: false,
                error: 'Los teléfonos fijos (inicio 2) no son compatibles con WhatsApp',
                normalized: null,
            };
        }

        return {
            isValid: false,
            error: 'Número inválido. Debe ser celular hondureño (inicia con 3, 8 o 9)',
            normalized: null,
        };
    }

    // Normalize: Extract only digits
    const normalized = cleaned.replace(/\D/g, '');

    // Remove country code if present for consistent storage
    const withoutCountryCode = normalized.startsWith('504')
        ? normalized.slice(3)
        : normalized;

    return {
        isValid: true,
        error: null,
        normalized: withoutCountryCode,
    };
}

/**
 * Format a phone number for display
 *
 * @param {string} phone - Raw phone input
 * @returns {string} Formatted phone (e.g., "9999-1234")
 */
export function formatHondurasPhone(phone) {
    if (!phone) return '';

    const digits = phone.replace(/\D/g, '');

    // Remove country code if present
    const local = digits.startsWith('504') ? digits.slice(3) : digits;

    // Format as XXXX-XXXX
    if (local.length === 8) {
        return `${local.slice(0, 4)}-${local.slice(4)}`;
    }

    return local;
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EMAIL VALIDATION
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Basic email validation regex
 * Not RFC 5322 compliant but covers 99% of real-world cases
 */
export const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Validate an email address
 *
 * @param {string} email - Email to validate
 * @returns {Object} Validation result
 */
export function validateEmail(email) {
    if (!email || typeof email !== 'string') {
        return { isValid: true, error: null }; // Email is optional
    }

    const cleaned = email.trim();

    if (cleaned && !EMAIL_REGEX.test(cleaned)) {
        return {
            isValid: false,
            error: 'Correo electrónico inválido',
        };
    }

    return { isValid: true, error: null };
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ADDRESS VALIDATION
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Validate a delivery address
 *
 * @param {string} address - Address to validate
 * @param {boolean} required - Whether address is required
 * @returns {Object} Validation result
 */
export function validateAddress(address, required = false) {
    if (!required && (!address || !address.trim())) {
        return { isValid: true, error: null };
    }

    if (required && (!address || address.trim().length < 5)) {
        return {
            isValid: false,
            error: 'Dirección demasiado corta (mínimo 5 caracteres)',
        };
    }

    return { isValid: true, error: null };
}
