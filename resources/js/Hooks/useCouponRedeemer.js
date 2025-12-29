import { router } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback, useMemo } from 'react';

/**
 * useCouponRedeemer - Coupon Management Hook (v9 - ADR-008/009 Compliant)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADR-008: LIFECYCLE CONTRACT                                            ║
 * ║  - Cannot use AbortController (Inertia router limitation)               ║
 * ║  - MITIGATION: Request poisoning prevents zombie state updates          ║
 * ║                                                                          ║
 * ║  ADR-009: COMPLEXITY DOCUMENTATION                                       ║
 * ║  - This hook manages: concurrency, timeouts, network detection          ║
 * ║  - See ARCHITECTURE section below                                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * ARCHITECTURE:
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. CONCURRENCY CONTROL
 *    - isProcessing ref prevents double-submit
 *    - Synchronous lock check in handler
 *
 * 2. REQUEST POISONING
 *    - Each request gets unique ID
 *    - Timeout "poisons" the ID
 *    - onFinish ignores poisoned requests
 *    - Prevents zombie callbacks from updating state
 *
 * 3. ADAPTIVE TIMEOUT
 *    - Uses navigator.connection API (Chrome/Edge only)
 *    - Falls back to 5000ms for unsupported browsers
 *    - Extends to 10000ms for 2G connections
 *
 * EDGE CASES:
 * ─────────────────────────────────────────────────────────────────────────────
 * - User spams submit: First request wins, others ignored
 * - Network timeout: Request poisoned, user sees error, can retry
 * - Component unmounts: Cleanup clears timeouts and refs
 * - SSR: navigator check returns fallback timeout
 *
 * ASSUMPTIONS:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Backend responds within timeout (5-10s depending on connection)
 * - Inertia router handles CSRF and redirects
 * - Route names are registered (applyCouponRoute, removeCouponRoute)
 *
 * @param {Object} options - Hook configuration
 * @param {string} options.applyCouponRoute - Route name for applying coupons
 * @param {string} options.removeCouponRoute - Route name for removing coupons
 * @returns {Object} Hook state and handlers
 */
export default function useCouponRedeemer({
    applyCouponRoute = 'checkout.apply-coupon',
    removeCouponRoute = 'checkout.remove-coupon',
} = {}) {
    // ═══════════════════════════════════════════════════════════════════════════
    // STATE (UI-driven, React-managed)
    // ═══════════════════════════════════════════════════════════════════════════
    const [code, setCode] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const [isShaking, setIsShaking] = useState(false);
    const [isTakingLong, setIsTakingLong] = useState(false);  // Latency feedback

    // ═══════════════════════════════════════════════════════════════════════════
    // REFS (Mutable, synchronous, for async callback access)
    // ═══════════════════════════════════════════════════════════════════════════
    const timeoutRef = useRef(null);
    const shakeTimeoutRef = useRef(null); // Separate ref for shake animation
    const slowConnectionTimeoutRef = useRef(null); // Latency feedback timer
    const isLoadingRef = useRef(false);      // Mirror for async callbacks
    const isProcessing = useRef(false);       // Concurrency lock
    const currentRequestId = useRef(null);    // Active request tracker
    const poisonedRequestIds = useRef(new Set()); // Timed-out requests
    const isMounted = useRef(true);           // Unmount detection (ADR-008)

    // ═══════════════════════════════════════════════════════════════════════════
    // SSR-SAFE NETWORK DETECTION (ADR-008)
    // ═══════════════════════════════════════════════════════════════════════════
    const getAdaptiveTimeout = useCallback(() => {
        // SSR Guard: No window/navigator during server render
        if (typeof window === 'undefined' || typeof navigator === 'undefined') {
            return 5000;
        }

        // Feature detection: navigator.connection is Chrome/Edge only
        // Safari: ❌  Firefox: ❌  iOS: ❌
        const connection = navigator.connection;
        if (!connection || !connection.effectiveType) {
            return 5000; // Fallback for unsupported browsers
        }

        // Adaptive timeout based on connection quality
        switch (connection.effectiveType) {
            case 'slow-2g':
            case '2g':
                return 10000; // 10s for very slow connections
            case '3g':
                return 7000;  // 7s for 3G
            case '4g':
            default:
                return 5000;  // 5s for 4G/WiFi/unknown
        }
    }, []);

    // ═══════════════════════════════════════════════════════════════════════════
    // CLEANUP (ADR-008: Memory safety)
    // ═══════════════════════════════════════════════════════════════════════════
    useEffect(() => {
        isMounted.current = true;

        return () => {
            isMounted.current = false;

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
            if (shakeTimeoutRef.current) {
                clearTimeout(shakeTimeoutRef.current);
            }
            if (slowConnectionTimeoutRef.current) {
                clearTimeout(slowConnectionTimeoutRef.current);
            }
            currentRequestId.current = null;
            poisonedRequestIds.current.clear();
        };
    }, []);

    // Clear error on input change (UX)
    useEffect(() => {
        if (error && code) {
            setError(null);
        }
    }, [code, error]);

    // ═══════════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Generate unique request ID.
     * Uses timestamp + random to avoid collisions without global counter.
     */
    const generateRequestId = useCallback(() => {
        return `${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
    }, []);

    const triggerShake = useCallback(() => {
        setIsShaking(true);
        // Track shake timeout for cleanup (ADR-008)
        shakeTimeoutRef.current = setTimeout(() => {
            if (isMounted.current) {
                setIsShaking(false);
            }
        }, 500);
    }, []);

    const startLoading = useCallback(() => {
        const requestId = generateRequestId();
        currentRequestId.current = requestId;

        setIsLoading(true);
        setIsTakingLong(false); // Reset latency state
        isLoadingRef.current = true;
        isProcessing.current = true;

        // LATENCY FEEDBACK: Show "slow connection" message after 3s
        slowConnectionTimeoutRef.current = setTimeout(() => {
            if (isMounted.current && isLoadingRef.current) {
                setIsTakingLong(true);
            }
        }, 3000);

        return requestId;
    }, [generateRequestId]);

    const isRequestPoisoned = useCallback((requestId) => {
        return poisonedRequestIds.current.has(requestId);
    }, []);

    /**
     * Reset loading state, handling poisoned requests.
     * @returns {boolean} True if state was reset, false if request was poisoned
     */
    const resetLoadingState = useCallback((requestId) => {
        // ADR-008: Don't update state if component unmounted
        if (!isMounted.current) {
            return false;
        }

        if (isRequestPoisoned(requestId)) {
            poisonedRequestIds.current.delete(requestId);
            return false;
        }

        // Clear all timers
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }
        if (slowConnectionTimeoutRef.current) {
            clearTimeout(slowConnectionTimeoutRef.current);
            slowConnectionTimeoutRef.current = null;
        }

        setIsLoading(false);
        setIsTakingLong(false); // Reset latency feedback
        isLoadingRef.current = false;
        isProcessing.current = false;
        currentRequestId.current = null;
        poisonedRequestIds.current.clear(); // Memory hygiene

        return true;
    }, [isRequestPoisoned]);

    const startTimeout = useCallback((requestId) => {
        const timeout = getAdaptiveTimeout();

        timeoutRef.current = setTimeout(() => {
            // Only poison if this request is still active
            if (isLoadingRef.current && currentRequestId.current === requestId) {
                poisonedRequestIds.current.add(requestId);

                // ADR-008: Check mounted before state update
                if (isMounted.current) {
                    setError('La solicitud tardó demasiado. Intenta de nuevo.');
                    triggerShake();

                    setIsLoading(false);
                    isLoadingRef.current = false;
                    isProcessing.current = false;
                    currentRequestId.current = null;
                }
            }
        }, timeout);
    }, [getAdaptiveTimeout, triggerShake]);

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC HANDLERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Submit coupon code for validation.
     */
    const handleSubmit = useCallback((e) => {
        e?.preventDefault();

        // Concurrency lock (synchronous check)
        if (isProcessing.current || isLoading) {
            return;
        }

        const normalizedCode = code.trim().toUpperCase();

        if (!normalizedCode) {
            setError('Ingresa un código de cupón');
            triggerShake();
            return;
        }

        const requestId = startLoading();
        setError(null);
        startTimeout(requestId);

        router.post(
            route(applyCouponRoute),
            { code: normalizedCode },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (!isRequestPoisoned(requestId) && isMounted.current) {
                        setCode('');
                    }
                },
                onError: (errors) => {
                    if (!isRequestPoisoned(requestId) && isMounted.current) {
                        const errorMessage =
                            errors.error_message ||
                            errors.code ||
                            errors.message ||
                            'Cupón inválido o expirado';
                        setError(errorMessage);
                        triggerShake();
                    }
                },
                onFinish: () => {
                    resetLoadingState(requestId);
                },
            }
        );
    }, [
        code, isLoading, applyCouponRoute,
        startLoading, startTimeout, triggerShake, isRequestPoisoned, resetLoadingState
    ]);

    /**
     * Remove currently applied coupon.
     */
    const handleRemove = useCallback(() => {
        if (isProcessing.current || isLoading) {
            return;
        }

        const requestId = startLoading();
        startTimeout(requestId);

        router.post(
            route(removeCouponRoute),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    if (resetLoadingState(requestId)) {
                        setCode('');
                    }
                },
            }
        );
    }, [isLoading, removeCouponRoute, startLoading, startTimeout, resetLoadingState]);

    /**
     * Reset hook to initial state.
     */
    const reset = useCallback(() => {
        setCode('');
        setError(null);
        setIsLoading(false);
        setIsShaking(false);
        setIsTakingLong(false);
        isLoadingRef.current = false;
        isProcessing.current = false;
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }
        if (slowConnectionTimeoutRef.current) {
            clearTimeout(slowConnectionTimeoutRef.current);
            slowConnectionTimeoutRef.current = null;
        }
    }, []);

    // ═══════════════════════════════════════════════════════════════════════════
    // DERIVED STATE: Status message for UX feedback
    // ═══════════════════════════════════════════════════════════════════════════
    const statusMessage = useMemo(() => {
        if (!isLoading) return null;
        if (isTakingLong) return 'La conexión es lenta, estamos validando...';
        return 'Validando cupón...';
    }, [isLoading, isTakingLong]);

    // ═══════════════════════════════════════════════════════════════════════════
    // RETURN API
    // ═══════════════════════════════════════════════════════════════════════════
    return {
        // State
        code,
        setCode,
        isLoading,
        error,
        isShaking,
        isTakingLong,       // NEW: Latency feedback flag
        statusMessage,       // NEW: Derived status message for UI

        // Handlers
        handleSubmit,
        handleRemove,
        reset,

        // Derived
        isValid: code.trim().length > 0,
        canSubmit: !isLoading && code.trim().length > 0,
    };
}
