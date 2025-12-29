/**
 * Toast.jsx - Enterprise Toast Notification Component
 *
 * ARCHITECTURE:
 * - Centralized toast notification for the entire application
 * - WCAG AA compliant with proper ARIA attributes
 * - Auto-dismiss with cleanup to prevent memory leaks
 * - Supports success, error, warning, and info variants
 *
 * FEATURES:
 * - Animated entrance with smooth transitions
 * - Manual dismiss button
 * - Screen reader announcements
 * - Keyboard accessible
 *
 * USAGE:
 * <Toast
 *     message="Item added"
 *     type="success"
 *     onClose={() => setToast(null)}
 *     primaryColor="#000"
 * />
 *
 * @module Components/Toast
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { AlertCircle, CheckCircle, Info, AlertTriangle, X } from 'lucide-react';

/**
 * Toast variant configurations
 */
const TOAST_VARIANTS = {
    success: {
        icon: CheckCircle,
        defaultColor: '#22c55e', // green-500
        ariaRole: 'status',
    },
    error: {
        icon: AlertCircle,
        defaultColor: '#ef4444', // red-500
        ariaRole: 'alert',
    },
    warning: {
        icon: AlertTriangle,
        defaultColor: '#f59e0b', // amber-500
        ariaRole: 'alert',
    },
    info: {
        icon: Info,
        defaultColor: '#3b82f6', // blue-500
        ariaRole: 'status',
    },
};

/**
 * Toast - Accessible notification component
 *
 * @param {Object} props
 * @param {string} props.message - The message to display
 * @param {'success'|'error'|'warning'|'info'} [props.type='success'] - Toast variant
 * @param {Function} props.onClose - Callback when toast is dismissed
 * @param {string} [props.primaryColor] - Override background color (uses variant default if not provided for non-error)
 * @param {number} [props.duration=3000] - Auto-dismiss duration in ms (0 to disable)
 * @param {string} [props.position='top'] - Position: 'top' | 'bottom'
 *
 * @returns {JSX.Element}
 */
export default function Toast({
    message,
    type = 'success',
    onClose,
    primaryColor,
    duration = 3000,
    position = 'top',
}) {
    const timeoutRef = useRef(null);

    // Get variant configuration
    const variant = TOAST_VARIANTS[type] || TOAST_VARIANTS.success;
    const Icon = variant.icon;

    // Determine background color
    // For 'error' always use red, for others use primaryColor if provided
    const bgColor = type === 'error'
        ? variant.defaultColor
        : (primaryColor || variant.defaultColor);

    // ═══════════════════════════════════════════════════════════════════════
    // AUTO-DISMISS with proper cleanup
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        if (duration > 0) {
            timeoutRef.current = setTimeout(() => {
                onClose?.();
            }, duration);
        }

        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, [duration, onClose]);

    // Handle manual dismiss
    const handleDismiss = () => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }
        onClose?.();
    };

    // Position classes
    const positionClasses = position === 'bottom'
        ? 'bottom-20'
        : 'top-20';

    return (
        <div
            role={variant.ariaRole}
            aria-live={type === 'error' ? 'assertive' : 'polite'}
            aria-atomic="true"
            className={`fixed ${positionClasses} left-1/2 -translate-x-1/2 z-50 animate-slide-in-down`}
        >
            <div
                className="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-white min-w-[200px] max-w-[90vw]"
                style={{ backgroundColor: bgColor }}
            >
                <Icon className="h-5 w-5 flex-shrink-0" aria-hidden="true" />
                <span className="font-medium flex-1">{message}</span>
                <button
                    type="button"
                    onClick={handleDismiss}
                    className="p-1 hover:bg-white/20 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
                    aria-label="Cerrar notificación"
                >
                    <X className="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </div>
    );
}

/**
 * useToast - Custom hook for toast state management
 *
 * USAGE:
 * const { toast, showToast, hideToast } = useToast();
 *
 * showToast('Success!', 'success');
 *
 * {toast && <Toast {...toast} onClose={hideToast} />}
 */
export function useToast() {
    const [toast, setToast] = useState(null);
    const timeoutRef = useRef(null);

    const showToast = useCallback((message, type = 'success', duration = 3000) => {
        // Clear any existing timeout
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }

        setToast({ message, type, duration });

        if (duration > 0) {
            timeoutRef.current = setTimeout(() => {
                setToast(null);
                timeoutRef.current = null;
            }, duration);
        }
    }, []);

    const hideToast = useCallback(() => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }
        setToast(null);
    }, []);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    return { toast, showToast, hideToast };
}
