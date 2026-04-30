import { useCallback, useEffect, useRef } from 'react';
import { toast as sonnerToast } from 'sonner';

const successSound = typeof window !== 'undefined'
    ? new Audio('/sounds/notification_success.mp3')
    : null;

function showNotification(notification) {
    const type = notification.type || 'success';
    const show = sonnerToast[type] ?? sonnerToast;
    const actionUrl = notification.action_url;

    show(notification.title || 'Notificación', {
        description: notification.body || undefined,
        duration: notification.duration || 10000,
        action: actionUrl
            ? {
                label: notification.action_label || 'Ver',
                onClick: () => window.location.assign(actionUrl),
            }
            : undefined,
    });
}

export function useWebSocketNotifications(user) {
    const channelRef = useRef(null);

    const addToast = useCallback((notification) => {
        showNotification(notification);
    }, []);

    const removeToast = useCallback((id) => {
        sonnerToast.dismiss(id);
    }, []);

    const clearAll = useCallback(() => {
        sonnerToast.dismiss();
    }, []);

    useEffect(() => {
        if (!user?.id) {
            return undefined;
        }

        if (typeof window === 'undefined' || typeof window.Echo === 'undefined') {
            console.warn('[WebSocket] Echo not available. Real-time notifications disabled.');
            return undefined;
        }

        const channelName = `user.${user.id}`;

        try {
            channelRef.current = window.Echo.private(channelName);

            channelRef.current
                .subscribed(() => {
                    console.log(`[WebSocket] Connected to private channel: ${channelName}`);
                })
                .error((error) => {
                    console.error(`[WebSocket] Subscription failed for ${channelName}:`, error);
                });

            channelRef.current.listen('.export.ready', (event) => {
                if (successSound) {
                    successSound.play().catch(() => {});
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

                addToast(notification);

                if (typeof window.__notificationBellHandler === 'function') {
                    window.__notificationBellHandler(notification);
                }
            });

            channelRef.current.listen('.export.failed', (event) => {
                addToast({
                    type: 'error',
                    title: event.title,
                    body: event.body,
                    duration: event.duration || 15000,
                    auto_dismiss: event.auto_dismiss,
                });
            });

            if (window.Echo?.connector?.pusher) {
                window.Echo.connector.pusher.connection.bind('state_change', (states) => {
                    if (states.current === 'unavailable' || states.current === 'disconnected') {
                        addToast({
                            type: 'warning',
                            title: 'Conexión inestable',
                            body: 'Intentando reconectar al servidor...',
                            duration: 5000,
                            auto_dismiss: true,
                        });
                    } else if (states.current === 'connected' && states.previous === 'unavailable') {
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

        return () => {
            if (!channelRef.current) {
                return;
            }

            try {
                window.Echo?.leave(channelName);
            } catch (error) {
                console.warn('[WebSocket] Error leaving channel:', error);
            }

            channelRef.current = null;
        };
    }, [user?.id, addToast]);

    return {
        toasts: [],
        removeToast,
        clearAll,
    };
}
