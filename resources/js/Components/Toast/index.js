import { toast as sonnerToast } from 'sonner';

function showToast(type, message, options = {}) {
    const description = options.body ?? options.description;
    const duration = options.duration;
    const action = options.actionUrl
        ? {
            label: options.actionLabel ?? 'Ver',
            onClick: () => {
                window.location.assign(options.actionUrl);
            },
        }
        : options.action;

    const payload = {
        ...options,
        description,
        duration,
        action,
    };

    delete payload.body;
    delete payload.actionUrl;
    delete payload.actionLabel;
    delete payload.autoDismiss;

    return (sonnerToast[type] ?? sonnerToast)(message, payload);
}

export const toast = {
    success(message, options = {}) {
        return showToast('success', message, options);
    },
    error(message, options = {}) {
        return showToast('error', message, options);
    },
    info(message, options = {}) {
        return showToast('info', message, options);
    },
    warning(message, options = {}) {
        return showToast('warning', message, options);
    },
};
