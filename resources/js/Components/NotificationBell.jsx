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

export function NotificationBell({ onNewNotification }) {
    // ═══════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
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
        } catch (error) {
            console.error('[NotificationBell] Hydration failed:', error);
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
            case 'export_ready': return <Download className="w-4 h-4 text-emerald-500" />;
            case 'export_failed': return <AlertCircle className="w-4 h-4 text-red-500" />;
            default: return <FileText className="w-4 h-4 text-blue-500" />;
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
                className="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <Bell className="w-5 h-5" />
                {unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-xs font-bold text-white bg-red-500 rounded-full">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div
                    className="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-xl z-[9999] overflow-hidden"
                    style={{ fontFamily: 'Inter, sans-serif' }}
                >
                    <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 className="font-semibold text-gray-900">Notificaciones</h3>
                        {unreadCount > 0 && (
                            <button
                                onClick={markAllAsRead}
                                className="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                            >
                                <CheckCheck className="w-3.5 h-3.5" /> Marcar todas
                            </button>
                        )}
                    </div>

                    <div className="max-h-80 overflow-y-auto">
                        {isLoading ? (
                            <div className="flex justify-center py-8"><div className="w-6 h-6 border-2 border-indigo-500 rounded-full animate-spin border-t-transparent" /></div>
                        ) : notifications.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-gray-400">
                                <Bell className="w-8 h-8 mb-2" />
                                <p className="text-sm">Sin notificaciones</p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {notifications.map((n) => (
                                    <li key={n.id} className={`px-4 py-3 hover:bg-gray-50 transition ${!n.read_at ? 'bg-indigo-50/50' : ''}`}>
                                        <div className="flex gap-3">
                                            <div className="mt-0.5">{getIcon(n.type)}</div>
                                            <div className="flex-1 min-w-0">
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
                                                <button onClick={() => markAsRead(n.id)} className="text-gray-400 hover:text-indigo-600 p-1">
                                                    <Check className="w-4 h-4" />
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    {notifications.length > 0 && (
                        <div className="px-4 py-2 border-t border-gray-100 bg-gray-50 text-center">
                            <button onClick={() => setIsOpen(false)} className="text-xs text-gray-500 hover:text-gray-700 w-full">Cerrar</button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
