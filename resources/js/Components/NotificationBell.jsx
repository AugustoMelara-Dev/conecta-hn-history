import { useCallback, useEffect, useRef, useState } from 'react';
import { AlertCircle, Bell, Check, CheckCheck, Download, FileText } from 'lucide-react';
import axios from 'axios';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import { cn } from '@/lib/utils';

const NOTIFICATIONS_ENDPOINT = '/api/v1/notifications';

export function NotificationBell({ onNewNotification }) {
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [isUnavailable, setIsUnavailable] = useState(false);
    const dropdownRef = useRef(null);

    const fetchNotifications = useCallback(async () => {
        if (isUnavailable) {
            return;
        }

        setIsLoading(true);

        try {
            const response = await axios.get(NOTIFICATIONS_ENDPOINT, {
                params: { limit: 10 },
            });

            setNotifications(response.data.notifications || []);
            setUnreadCount(response.data.unread_count || 0);
            setIsUnavailable(false);
        } catch (error) {
            const status = error?.response?.status;

            if ([401, 403, 404].includes(status)) {
                setNotifications([]);
                setUnreadCount(0);
                setIsUnavailable(true);
                return;
            }

            console.error('[NotificationBell] Hydration failed:', error);
        } finally {
            setIsLoading(false);
        }
    }, [isUnavailable]);

    const handleWebSocketNotification = useCallback((notification) => {
        setUnreadCount((current) => current + 1);

        if (notification) {
            setNotifications((current) => [
                {
                    id: notification.id || `ws-${Date.now()}`,
                    type: notification.type || 'general',
                    title: notification.title || 'Nueva notificacion',
                    body: notification.body || '',
                    action_url: notification.action_url || null,
                    read_at: null,
                    created_at: new Date().toISOString(),
                    time_ago: 'Ahora',
                },
                ...current.slice(0, 9),
            ]);
        }

        onNewNotification?.(notification);
    }, [onNewNotification]);

    useEffect(() => {
        window.__notificationBellHandler = handleWebSocketNotification;

        return () => {
            delete window.__notificationBellHandler;
        };
    }, [handleWebSocketNotification]);

    const markAsRead = async (id) => {
        try {
            const response = await axios.patch(`${NOTIFICATIONS_ENDPOINT}/${id}/read`);

            setUnreadCount(response.data.unread_count);
            setNotifications((current) =>
                current.map((notification) =>
                    notification.id === id
                        ? { ...notification, read_at: new Date().toISOString() }
                        : notification
                )
            );
        } catch (error) {
            console.error('[NotificationBell] Mark read failed:', error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.patch(`${NOTIFICATIONS_ENDPOINT}/read-all`);
            setUnreadCount(0);
            setNotifications((current) =>
                current.map((notification) => ({ ...notification, read_at: new Date().toISOString() }))
            );
        } catch (error) {
            console.error('[NotificationBell] Mark all failed:', error);
        }
    };

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [isOpen]);

    const getIcon = (type) => {
        switch (type) {
            case 'export_ready':
                return <Download className="size-4 text-primary" aria-hidden="true" />;
            case 'export_failed':
                return <AlertCircle className="size-4 text-destructive" aria-hidden="true" />;
            default:
                return <FileText className="size-4 text-muted-foreground" aria-hidden="true" />;
        }
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => {
                    setIsOpen(!isOpen);

                    if (!isOpen) {
                        fetchNotifications();
                    }
                }}
                className="relative"
                aria-label={`Abrir notificaciones${unreadCount > 0 ? ` (${unreadCount} sin leer)` : ''}`}
            >
                <Bell className="size-5" aria-hidden="true" />
                {unreadCount > 0 && (
                    <Badge className="absolute -right-1 -top-1 h-[18px] min-w-[18px] justify-center rounded-full bg-destructive px-1 text-[10px] text-destructive-foreground">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </Badge>
                )}
            </Button>

            {isOpen && (
                <Card className="absolute right-0 z-[9999] mt-2 w-80 overflow-hidden shadow-xl">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 px-4 py-3">
                        <CardTitle className="text-sm">Notificaciones</CardTitle>
                        {unreadCount > 0 && (
                            <Button type="button" variant="ghost" size="sm" onClick={markAllAsRead}>
                                <CheckCheck className="size-4" aria-hidden="true" />
                                Marcar todas
                            </Button>
                        )}
                    </CardHeader>
                    <Separator />

                    <CardContent className="max-h-80 overflow-y-auto p-0">
                        {isLoading ? (
                            <div className="flex justify-center py-8">
                                <Spinner />
                            </div>
                        ) : isUnavailable ? (
                            <div className="flex flex-col items-center justify-center gap-2 px-6 py-8 text-center text-muted-foreground">
                                <Bell className="size-8" aria-hidden="true" />
                                <p className="text-sm font-medium text-foreground">Notificaciones no disponibles</p>
                                <p className="text-xs">
                                    El centro se activara cuando el API este disponible para esta sesion.
                                </p>
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 px-6 py-8 text-center text-muted-foreground">
                                <Bell className="size-8" aria-hidden="true" />
                                <p className="text-sm font-medium text-foreground">Sin notificaciones</p>
                                <p className="text-xs">Cuando haya novedades importantes apareceran aqui.</p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {notifications.map((notification) => (
                                    <li
                                        key={notification.id}
                                        className={cn(
                                            'px-4 py-3 transition-colors hover:bg-muted/60',
                                            !notification.read_at && 'bg-muted/50'
                                        )}
                                    >
                                        <div className="flex gap-3">
                                            <div className="mt-0.5">{getIcon(notification.type)}</div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-foreground">
                                                    {notification.title}
                                                </p>
                                                <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                                    {notification.body}
                                                </p>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <span className="text-xs text-muted-foreground">
                                                        {notification.time_ago}
                                                    </span>
                                                    {notification.action_url && (
                                                        <a
                                                            href={notification.action_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            onClick={() => !notification.read_at && markAsRead(notification.id)}
                                                            className="text-xs font-medium text-primary underline-offset-4 hover:underline"
                                                        >
                                                            Descargar
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                            {!notification.read_at && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => markAsRead(notification.id)}
                                                    aria-label="Marcar notificacion como leida"
                                                >
                                                    <Check className="size-4" aria-hidden="true" />
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>

                    {notifications.length > 0 && (
                        <CardFooter className="border-t p-2">
                            <Button type="button" variant="ghost" size="sm" onClick={() => setIsOpen(false)} className="w-full">
                                Cerrar
                            </Button>
                        </CardFooter>
                    )}
                </Card>
            )}
        </div>
    );
}
