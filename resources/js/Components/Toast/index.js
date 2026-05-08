import { toast as sonnerToast } from 'sonner';
import { emit, subscribeToToasts } from './toastService';

export { ToastNotification } from './ToastNotification';
export { ToastContainer } from './ToastContainer';
export { ToastProvider } from './ToastProvider';
export { subscribeToToasts, emit };

const normalizeToastOptions = (options = {}, fallbackDuration = 5000) => ({
    description: options.body || options.description,
    duration: options.duration || fallbackDuration,
    action: options.action,
    id: options.id,
});

export const toast = {
    success(message, options = {}) {
        sonnerToast.success(message, normalizeToastOptions(options, 5000));
    },

    error(message, options = {}) {
        sonnerToast.error(message, normalizeToastOptions(options, 8000));
    },

    info(message, options = {}) {
        sonnerToast.info(message, normalizeToastOptions(options, 5000));
    },

    warning(message, options = {}) {
        sonnerToast.warning(message, normalizeToastOptions(options, 6000));
    },

    dismiss(id) {
        sonnerToast.dismiss(id);
    },
};
