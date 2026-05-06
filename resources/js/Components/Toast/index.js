/**
 * Toast/index.js - Public API for Toast components with Imperative Service
 * 
 * ARCHITECTURE:
 * - Event-based singleton for imperative toast calls
 * - React components for rendering
 * - Decoupled: service emits events, container listens
 * 
 * USAGE (Imperative):
 * import { toast } from '@/Components/Toast';
 * toast.success("Saved!");
 * toast.error("Failed!");
 * toast.info("Loading...");
 * 
 * USAGE (In App Root):
 * import { ToastProvider } from '@/Components/Toast';
 * <ToastProvider />
 */

// Import emit for internal use (must be before exports in ESM)
import { emit, subscribeToToasts } from './toastService';

// Export React components
export { ToastNotification } from './ToastNotification';
export { ToastContainer } from './ToastContainer';
export { ToastProvider } from './ToastProvider';

// Re-export service functions for external use
export { subscribeToToasts, emit };

/**
 * Imperative Toast API
 * 
 * @example
 * toast.success("Item saved");
 * toast.error("Something went wrong");
 * toast.info("Processing...");
 * toast.warning("Are you sure?");
 */
export const toast = {
    /**
     * Show a success toast
     * @param {string} message - Message to display
     * @param {Object} [options] - Additional options
     */
    success(message, options = {}) {
        emit({
            type: 'success',
            title: message,
            body: options.body || null,
            duration: options.duration || 5000,
            autoDismiss: options.autoDismiss !== false,
            ...options,
        });
    },

    /**
     * Show an error toast
     * @param {string} message - Message to display
     * @param {Object} [options] - Additional options
     */
    error(message, options = {}) {
        emit({
            type: 'error',
            title: message,
            body: options.body || null,
            duration: options.duration || 8000, // Errors stay longer
            autoDismiss: options.autoDismiss !== false,
            ...options,
        });
    },

    /**
     * Show an info toast
     * @param {string} message - Message to display
     * @param {Object} [options] - Additional options
     */
    info(message, options = {}) {
        emit({
            type: 'info',
            title: message,
            body: options.body || null,
            duration: options.duration || 5000,
            autoDismiss: options.autoDismiss !== false,
            ...options,
        });
    },

    /**
     * Show a warning toast
     * @param {string} message - Message to display
     * @param {Object} [options] - Additional options
     */
    warning(message, options = {}) {
        emit({
            type: 'warning',
            title: message,
            body: options.body || null,
            duration: options.duration || 6000,
            autoDismiss: options.autoDismiss !== false,
            ...options,
        });
    },
};
