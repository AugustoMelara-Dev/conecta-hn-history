/**
 * NotificationBell.jsx - Hybrid Notification Center
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURE: HYBRID STATE MANAGEMENT                                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Source 1 (History): API /api/notifications (Hydrated Links)             ║
 * ║  Source 2 (Real-Time): WebSocket 'export.ready' (Ephemeral Links)        ║
 * ║  Strategy: Merge both sources into a single chronological timeline.      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { Bell, Check, CheckCheck, X, Download, AlertCircle, FileText } from 'lucide-react';
import axios from 'axios';
import { toast } from '@/Components/Toast/index.js';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';

export function NotificationBell({ onNewNotification }) {
    // ═══════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [loadError, setLoadError] = useState('');
    const dropdownRef = useRef(null);

    // ═══════════════════════════════════════════════════════════════════════
    // HYDRATION SOURCE: API (History)
    // ═══════════════════════════════════════════════════════════════════════
    const fetchNotifications = useCallback(async () => {
        setIsLoading(true);
        try {
            const response = await axios.get('/api/notifications', {
                params: { limit: 10 },
            });
            setNotifications(response.data.notifications || []);
            setUnreadCount(response.data.unread_count || 0);
            setLoadError('');
        } catch (error) {
            console.error('[NotificationBell] Hydration failed:', error);
            setLoadError('No pudimos cargar tus notificaciones. Reintenta en unos segundos.');
            toast.error('No pudimos cargar tus notificaciones. Reintenta desde la campana.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    // Initial Hydration
    useEffect(() => {
        fetchNotifications();
    }, [fetchNotifications]);

    // ═══════════════════════════════════════════════════════════════════════
    // RESILIENCE: Revalidate on Window Focus (Avoids Dead Links in SPA)
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        const handleFocus = () => {
            fetchNotifications(); // Refresh links when user comes back
        };
        window.addEventListener('focus', handleFocus);
        return () => window.removeEventListener('focus', handleFocus);
    }, [fetchNotifications]);

    // ═══════════════════════════════════════════════════════════════════════
    // REAL-TIME SOURCE: WebSocket (Live Events)
    // ═══════════════════════════════════════════════════════════════════════
    const handleWebSocketNotification = useCallback((notification) => {
        setUnreadCount((prev) => prev + 1);

        // Inject new item at top of list (unshift)
        if (notification) {
            setNotifications((prev) => [
                {
                    id: notification.id || `ws-${Date.now()}`,
                    type: notification.type || 'general',
                    title: notification.title || 'Nueva notificación',
                    body: notification.body || '',
                    action_url: notification.action_url || null, // Ephemeral URL from broadcast
                    read_at: null,
                    created_at: new Date().toISOString(),
                    time_ago: 'Ahora',
                },
                ...prev.slice(0, 9), // Keep max 10
            ]);
        }
        onNewNotification?.(notification);
    }, [onNewNotification]);

    // Global Handler for Hook Integration
    useEffect(() => {
        window.__notificationBellHandler = handleWebSocketNotification;
        return () => {
            delete window.__notificationBellHandler;
        };
    }, [handleWebSocketNotification]);

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════
    const markAsRead = async (id) => {
        try {
            const response = await axios.patch(`/api/notifications/${id}/read`);
            setUnreadCount(response.data.unread_count);
            setNotifications((prev) =>
                prev.map((n) => n.id === id ? { ...n, read_at: new Date().toISOString() } : n)
            );
        } catch (error) {
            console.error('[NotificationBell] Mark read failed:', error);
            toast.warning('No pudimos marcar esa notificación como leída. Intenta de nuevo.');
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.patch('/api/notifications/read-all');
            setUnreadCount(0);
            setNotifications((prev) =>
                prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
            );
        } catch (error) {
            console.error('[NotificationBell] Mark all failed:', error);
            toast.warning('No pudimos marcar todas las notificaciones. Intenta de nuevo.');
        }
    };

    // Click Outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        if (isOpen) document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [isOpen]);

    // Icon Helper
    const getIcon = (type) => {
        switch (type) {
            case 'export_ready': return <Download className="size-4 text-emerald-500" aria-hidden="true" />;
            case 'export_failed': return <AlertCircle className="size-4 text-red-500" aria-hidden="true" />;
            default: return <FileText className="size-4 text-blue-500" aria-hidden="true" />;
        }
    };

    // ═══════════════════════════════════════════════════════════════════════
    // UI RENDER
    // ═══════════════════════════════════════════════════════════════════════
    return (
        <div className="relative" ref={dropdownRef}>
            <button
                type="button"
                onClick={() => {
                    setIsOpen(!isOpen);
                    if (!isOpen) fetchNotifications();
                }}
                className="relative rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                aria-label={unreadCount > 0 ? `Abrir notificaciones, ${unreadCount} sin leer` : 'Abrir notificaciones'}
                aria-expanded={isOpen}
            >
                <Bell className="size-5" aria-hidden="true" />
                {unreadCount > 0 && (
                    <span className="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white" aria-live="polite">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div
                    className="absolute right-0 z-[9999] mt-2 w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl"
                    style={{ fontFamily: 'Inter, sans-serif' }}
                    role="dialog"
                    aria-label="Centro de notificaciones"
                >
                    <div className="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-3">
                        <h3 className="font-semibold text-gray-900">Notificaciones</h3>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllAsRead}
                                className="flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            >
                                <CheckCheck className="size-3.5" aria-hidden="true" /> Marcar todas
                            </button>
                        )}
                    </div>

                    <div className="max-h-80 overflow-y-auto" aria-live="polite">
                        {isLoading ? (
                            <div className="flex flex-col gap-3 p-4" data-testid="notification-loading-state">
                                {[0, 1, 2].map((item) => (
                                    <div key={item} className="flex items-start gap-3">
                                        <Skeleton className="size-8 rounded-full" />
                                        <div className="flex-1 space-y-2">
                                            <Skeleton className="h-4 w-3/4" />
                                            <Skeleton className="h-3 w-full" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : loadError ? (
                            <div className="flex flex-col items-center justify-center gap-3 px-4 py-8 text-center text-gray-500" data-testid="notification-error-state">
                                <AlertCircle className="size-8 text-amber-500" aria-hidden="true" />
                                <p className="text-sm">{loadError}</p>
                                <Button type="button" variant="outline" size="sm" onClick={fetchNotifications}>
                                    Reintentar
                                </Button>
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-gray-400" data-testid="notification-empty-state">
                                <Bell className="mb-2 size-8" aria-hidden="true" />
                                <p className="text-sm">Sin notificaciones</p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {notifications.map((n) => (
                                    <li key={n.id} className={`px-4 py-3 hover:bg-gray-50 transition ${!n.read_at ? 'bg-indigo-50/50' : ''}`}>
                                        <div className="flex gap-3">
                                            <div className="mt-0.5">{getIcon(n.type)}</div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-gray-900 truncate">{n.title}</p>
                                                <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{n.body}</p>
                                                <div className="flex items-center gap-2 mt-1">
                                                    <span className="text-xs text-gray-400">{n.time_ago}</span>
                                                    {n.action_url && (
                                                        <a
                                                            href={n.action_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            onClick={() => !n.read_at && markAsRead(n.id)}
                                                            className="text-xs text-indigo-600 hover:underline font-medium cursor-pointer"
                                                        >
                                                            Descargar
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                            {!n.read_at && (
                                                <button
                                                    type="button"
                                                    onClick={() => markAsRead(n.id)}
                                                    className="rounded-md p-1 text-gray-400 hover:text-indigo-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                                    aria-label={`Marcar ${n.title} como leída`}
                                                >
                                                    <Check className="size-4" aria-hidden="true" />
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    {notifications.length > 0 && (
                        <div className="border-t border-gray-100 bg-gray-50 px-4 py-2 text-center">
                            <button type="button" onClick={() => setIsOpen(false)} className="w-full text-xs text-gray-500 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Cerrar</button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
