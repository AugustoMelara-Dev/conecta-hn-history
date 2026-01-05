/**
 * ToastProvider.jsx - Global Toast Listener Component
 * 
 * ARCHITECTURE:
 * - Subscribes to imperative toast events
 * - Manages toast state array
 * - Renders ToastContainer with current toasts
 * 
 * USAGE (Mount once in App or Layout):
 * import { ToastProvider } from '@/Components/Toast';
 * // In your root component:
 * <Layout>
 *   {children}
 *   <ToastProvider />
 * </Layout>
 */

import { useState, useEffect, useCallback } from 'react';
import { ToastContainer } from './ToastContainer';
// IMPORTANT: Import from toastService to avoid circular dependency with index.js
import { subscribeToToasts } from './toastService';

/**
 * ToastProvider - Mounts the toast system
 * Place this component once at your app root.
 */
export function ToastProvider() {
    const [toasts, setToasts] = useState([]);

    // Subscribe to toast events on mount
    useEffect(() => {
        const unsubscribe = subscribeToToasts((newToast) => {
            setToasts((prev) => [...prev, newToast]);
        });

        return () => unsubscribe();
    }, []);

    // Dismiss handler
    const handleDismiss = useCallback((id) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    return <ToastContainer toasts={toasts} onDismiss={handleDismiss} />;
}
