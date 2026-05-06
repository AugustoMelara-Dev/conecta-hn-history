/**
 * toastService.js - Core Toast Event Service
 * 
 * ARCHITECTURE:
 * - Pure event emitter, no React dependencies
 * - Imported by ToastProvider for subscription
 * - Imported by index.js for the toast API
 * 
 * This file exists to break the circular dependency between
 * ToastProvider.jsx and index.js
 */

// ═══════════════════════════════════════════════════════════════════════════
// IMPERATIVE TOAST SERVICE (Singleton Pattern)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Event listeners for toast emissions
 * @type {Set<Function>}
 */
const listeners = new Set();

/**
 * Add a listener for toast events
 * @param {Function} callback - Function to call with toast data
 * @returns {Function} Unsubscribe function
 */
export function subscribeToToasts(callback) {
    listeners.add(callback);
    return () => listeners.delete(callback);
}

/**
 * Emit a toast to all listeners
 * @param {Object} toastData - Toast configuration
 */
export function emit(toastData) {
    const id = Date.now().toString(36) + Math.random().toString(36).substr(2);
    const payload = { id, ...toastData };
    listeners.forEach((callback) => callback(payload));
}
