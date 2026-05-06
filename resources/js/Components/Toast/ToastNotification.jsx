/**
 * ToastNotification.jsx - Atomic Toast Component (Corporate Minimalist)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DESIGN SYSTEM: Corporate Minimalist                                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - White background with left accent border                             ║
 * ║  - Inter/System font stack                                              ║
 * ║  - Subtle shadow (shadow-lg)                                            ║
 * ║  - Green accent for success, Red for error                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * FEATURES:
 * - Auto-dismiss timer (pauses on hover)
 * - Action button for downloads
 * - Smooth enter/exit animations
 * - WCAG AA accessible (role="alert")
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { X, CheckCircle, AlertCircle, Download } from 'lucide-react';

/**
 * Icon and color mapping by toast type
 */
const TOAST_CONFIG = {
    success: {
        Icon: CheckCircle,
        borderColor: 'border-l-emerald-500',
        iconColor: 'text-emerald-500',
    },
    error: {
        Icon: AlertCircle,
        borderColor: 'border-l-red-500',
        iconColor: 'text-red-500',
    },
    info: {
        Icon: CheckCircle,
        borderColor: 'border-l-blue-500',
        iconColor: 'text-blue-500',
    },
    warning: {
        Icon: AlertCircle,
        borderColor: 'border-l-amber-500',
        iconColor: 'text-amber-500',
    },
};

/**
 * ToastNotification - Individual toast notification component
 *
 * @param {Object} props
 * @param {string} props.id - Unique toast identifier
 * @param {'success'|'error'|'info'|'warning'} props.type - Toast variant
 * @param {string} props.title - Bold title text
 * @param {string} props.body - Description text
 * @param {string|null} props.actionUrl - URL for action button
 * @param {string} props.actionLabel - Action button label
 * @param {number} props.duration - Auto-dismiss duration in ms
 * @param {boolean} props.autoDismiss - Whether to auto-dismiss
 * @param {Function} props.onDismiss - Callback when dismissed
 */
export function ToastNotification({
    id,
    type = 'success',
    title,
    body,
    actionUrl,
    actionLabel = 'Descargar',
    duration = 10000,
    autoDismiss = true,
    onDismiss,
}) {
    // ═══════════════════════════════════════════════════════════════════════
    // STATE & REFS
    // ═══════════════════════════════════════════════════════════════════════
    const [isVisible, setIsVisible] = useState(false);
    const [isPaused, setIsPaused] = useState(false);
    const timerRef = useRef(null);
    const remainingTimeRef = useRef(duration);
    const startTimeRef = useRef(null);

    // Get config for this toast type
    const config = TOAST_CONFIG[type] || TOAST_CONFIG.success;
    const { Icon, borderColor, iconColor } = config;

    // ═══════════════════════════════════════════════════════════════════════
    // ANIMATION: Slide in on mount
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        // Trigger animation after mount
        const animationFrame = requestAnimationFrame(() => {
            setIsVisible(true);
        });

        return () => cancelAnimationFrame(animationFrame);
    }, []);

    // ═══════════════════════════════════════════════════════════════════════
    // AUTO-DISMISS TIMER (with pause on hover)
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        if (!autoDismiss || duration <= 0) {
            return;
        }

        const startTimer = () => {
            startTimeRef.current = Date.now();
            timerRef.current = setTimeout(() => {
                handleDismiss();
            }, remainingTimeRef.current);
        };

        // Start or resume timer when not paused
        if (!isPaused) {
            startTimer();
        }

        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);

                // Calculate remaining time for resume
                if (startTimeRef.current) {
                    const elapsed = Date.now() - startTimeRef.current;
                    remainingTimeRef.current = Math.max(0, remainingTimeRef.current - elapsed);
                }
            }
        };
    }, [isPaused, autoDismiss, duration]);

    // ═══════════════════════════════════════════════════════════════════════
    // HANDLERS
    // ═══════════════════════════════════════════════════════════════════════
    const handleDismiss = useCallback(() => {
        setIsVisible(false);
        // Wait for exit animation to complete
        setTimeout(() => {
            onDismiss?.(id);
        }, 300);
    }, [id, onDismiss]);

    const handleMouseEnter = () => {
        setIsPaused(true);
    };

    const handleMouseLeave = () => {
        setIsPaused(false);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════
    return (
        <div
            role="alert"
            aria-live={type === 'error' ? 'assertive' : 'polite'}
            aria-atomic="true"
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
            className={`
                w-96 max-w-[calc(100vw-2rem)]
                bg-white border border-gray-200 border-l-4 ${borderColor}
                rounded-r-lg shadow-lg
                transform transition-all duration-300 ease-out
                ${isVisible
                    ? 'translate-x-0 opacity-100'
                    : 'translate-x-full opacity-0'
                }
            `}
            style={{ fontFamily: 'Inter, system-ui, -apple-system, sans-serif' }}
        >
            <div className="p-4">
                {/* Header Row */}
                <div className="flex items-start gap-3">
                    {/* Icon */}
                    <Icon
                        className={`w-5 h-5 flex-shrink-0 mt-0.5 ${iconColor}`}
                        aria-hidden="true"
                    />

                    {/* Content */}
                    <div className="flex-1 min-w-0">
                        {/* Title */}
                        <p className="text-sm font-bold text-gray-900">
                            {title}
                        </p>

                        {/* Body */}
                        {body && (
                            <p className="mt-1 text-sm text-gray-600">
                                {body}
                            </p>
                        )}

                        {/* Action Button */}
                        {actionUrl && (
                            <a
                                href={actionUrl}
                                className="
                                    inline-flex items-center gap-1.5 mt-3
                                    text-sm font-medium text-indigo-600
                                    hover:text-indigo-800 transition-colors
                                    focus:outline-none focus:underline
                                "
                            >
                                <Download className="w-4 h-4" aria-hidden="true" />
                                {actionLabel}
                            </a>
                        )}
                    </div>

                    {/* Close Button */}
                    <button
                        type="button"
                        onClick={handleDismiss}
                        className="
                            p-1.5 text-gray-400 hover:text-gray-600
                            rounded-md hover:bg-gray-100 transition-colors
                            focus:outline-none focus:ring-2 focus:ring-gray-300
                        "
                        aria-label="Cerrar notificación"
                    >
                        <X className="w-4 h-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>
    );
}
