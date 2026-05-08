/**
 * useWebSocketNotifications.js - Real-Time Notification Hook
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURE: Global WebSocket Event Listener for User Notifications   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Connects to private user channel: App.Models.User.{id}               ║
 * ║  - Listens for export.ready and export.failed events                    ║
 * ║  - Manages ephemeral toast state with auto-cleanup                      ║
 * ║  - Strict useEffect cleanup to prevent memory leaks                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @param {Object|null} user - Authenticated user object from Inertia props
 * @returns {{ toasts: Array, removeToast: Function, clearAll: Function }}
 */

import { useState, useEffect, useCallback, useRef } from 'react';

/**
 * Generate unique toast ID
 * @returns {string}
 */
const generateToastId = () =>
    `${Date.now()}-${Math.random().toString(36).substring(2, 11)}`;

/**
 * Audio feedback for notifications (cached outside component)
 * Sound file should be placed in: public/sounds/notification_success.mp3
 */
const successSound = typeof window !== 'undefined'
    ? new Audio('/sounds/notification_success.mp3')
    : null;

const realtimeEnabled = String(import.meta.env.VITE_REVERB_ENABLED ?? '').toLowerCase().trim() === 'true';

/**
 * useWebSocketNotifications - Custom Hook for WebSocket notifications
 * 
 * @param {Object|null} user - Authenticated user from usePage().props.auth.user
 */
export function useWebSocketNotifications(user) {
    const [toasts, setToasts] = useState([]);
    const channelRef = useRef(null);

    /**
     * Add a new toast to the queue
     */
    const addToast = useCallback((notification) => {
        const toast = {
            id: generateToastId(),
            type: notification.type || 'success',
            title: notification.title || 'Notificación',
            body: notification.body || '',
            actionUrl: notification.action_url || null,
            actionLabel: notification.action_label || 'Ver',
            duration: notification.duration || 10000,
            autoDismiss: notification.auto_dismiss !== false,
            timestamp: Date.now(),
        };

        // Limit stack to 5 toasts to prevent visual DoS
        setToasts((prev) => {
            const newStack = [...prev, toast];
            if (newStack.length > 5) {
                return newStack.slice(newStack.length - 5);
            }
            return newStack;
        });
    }, []);

    /**
     * Remove a specific toast by ID
     */
    const removeToast = useCallback((id) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    /**
     * Clear all toasts
     */
    const clearAll = useCallback(() => {
        setToasts([]);
    }, []);

    /**
     * WebSocket subscription lifecycle
     */
    useEffect(() => {
        // ═══════════════════════════════════════════════════════════════════
        // GUARD CLAUSES: Prevent execution if prerequisites not met
        // ═══════════════════════════════════════════════════════════════════
        if (!user?.id) {
            return;
        }

        if (!realtimeEnabled || typeof window === 'undefined' || typeof window.Echo === 'undefined') {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // CHANNEL SUBSCRIPTION
        // ═══════════════════════════════════════════════════════════════════
        // Channel name MUST match routes/channels.php authorization pattern
        // ChannelRegistry.USER_CHANNEL_PATTERN = 'user.{id}'
        const channelName = `user.${user.id}`;

        try {
            channelRef.current = window.Echo.private(channelName);

            // ═══════════════════════════════════════════════════════════════
            // OBSERVABILITY: Connection status callbacks
            // ═══════════════════════════════════════════════════════════════
            channelRef.current
                .subscribed(() => {
                    console.log(`[WebSocket] ✅ Connected to private channel: ${channelName}`);
                })
                .error((error) => {
                    console.error(`[WebSocket] ❌ Subscription failed for ${channelName}:`, error);
                    // Common cause: 403 = channel authorization failed in routes/channels.php
                });

            // Listen for export.ready events
            // NOTE: The leading dot is REQUIRED - Laravel Echo namespace convention
            channelRef.current.listen('.export.ready', (event) => {
                // Play success sound (handle autoplay restrictions gracefully)
                if (successSound) {
                    successSound.play().catch(() => { });
                }

                const notification = {
                    type: event.type || 'success',
                    title: event.title,
                    body: event.body,
                    action_url: event.action_url,
                    action_label: event.action_label,
                    duration: event.duration,
                    auto_dismiss: event.auto_dismiss,
                };

                // Show ephemeral toast
                addToast(notification);

                // Sync with NotificationBell (increment counter + prepend to list)
                if (typeof window.__notificationBellHandler === 'function') {
                    window.__notificationBellHandler(notification);
                }
            });

            // Listen for export.failed events
            channelRef.current.listen('.export.failed', (event) => {
                addToast({
                    type: 'error',
                    title: event.title,
                    body: event.body,
                    duration: event.duration || 15000,
                    auto_dismiss: event.auto_dismiss,
                });
            });

            // ═══════════════════════════════════════════════════════════════
            // RESILIENCE: Reconnection Monitoring
            // ═══════════════════════════════════════════════════════════════
            if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
                window.Echo.connector.pusher.connection.bind('state_change', (states) => {
                    if (states.current === 'unavailable' || states.current === 'disconnected') {
                        // Show warning toast for instability
                        addToast({
                            type: 'warning',
                            title: 'Conexión inestable',
                            body: 'Intentando reconectar al servidor...',
                            duration: 5000,
                            auto_dismiss: true,
                        });
                    } else if (states.current === 'connected' && states.previous === 'unavailable') {
                        // Connection restored
                        addToast({
                            type: 'success',
                            title: 'Conexión restaurada',
                            body: 'Sincronizado con el servidor.',
                            duration: 3000,
                            auto_dismiss: true,
                        });
                    }
                });
            }

        } catch (error) {
            console.error('[WebSocket] Failed to connect to channel:', error);
        }

        // ═══════════════════════════════════════════════════════════════════
        // CLEANUP: Leave channel on unmount or user change
        // CRITICAL: Prevents duplicate listeners and memory leaks
        // ═══════════════════════════════════════════════════════════════════
        return () => {
            if (channelRef.current) {
                try {
                    window.Echo?.leave(channelName);
                } catch (error) {
                    console.warn('[WebSocket] Error leaving channel:', error);
                }
                channelRef.current = null;
            }
        };
    }, [user?.id, addToast]);

    return {
        toasts,
        removeToast,
        clearAll,
    };
}
