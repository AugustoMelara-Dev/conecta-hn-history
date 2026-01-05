/**
 * ToastContainer.jsx - Fixed Position Toast Stack Container
 *
 * ARCHITECTURE:
 * - Fixed positioning (bottom-right, z-50)
 * - Renders stack of ToastNotification components
 * - Receives toasts array and dismiss handler from parent
 *
 * USAGE:
 * <ToastContainer toasts={toasts} onDismiss={removeToast} />
 */

import { ToastNotification } from './ToastNotification';

/**
 * ToastContainer - Container component for toast notifications
 *
 * @param {Object} props
 * @param {Array} props.toasts - Array of toast objects
 * @param {Function} props.onDismiss - Callback to dismiss a toast by ID
 */
export function ToastContainer({ toasts = [], onDismiss }) {
    // Don't render container if no toasts
    if (!toasts.length) {
        return null;
    }

    return (
        <div
            aria-label="Notificaciones"
            className="fixed bottom-4 right-4 z-[9999] flex flex-col gap-3"
        >
            {toasts.map((toast) => (
                <ToastNotification
                    key={toast.id}
                    id={toast.id}
                    type={toast.type}
                    title={toast.title}
                    body={toast.body}
                    actionUrl={toast.actionUrl}
                    actionLabel={toast.actionLabel}
                    duration={toast.duration}
                    autoDismiss={toast.autoDismiss}
                    onDismiss={onDismiss}
                />
            ))}
        </div>
    );
}
