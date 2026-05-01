import { Children, cloneElement, isValidElement } from 'react';
import { usePage } from '@inertiajs/react';
import { useUIStore } from '@/Stores/useUIStore';

/**
 * AuthGate - HOC that intercepts actions requiring authentication
 * 
 * @param {ReactNode} children - The component to wrap (typically a Button)
 * @param {Function} onAuth - Callback to execute if user is authenticated
 * @param {ReactNode} fallback - Optional fallback UI for non-authenticated state
 */
export default function AuthGate({ children, onAuth, fallback = null }) {
    const { auth } = usePage().props;
    const openLoginModal = useUIStore((state) => state.openLoginModal);

    const handleClick = (event, originalOnClick) => {
        // If user is NOT authenticated
        if (!auth.user) {
            event.preventDefault();
            event.stopPropagation();

            // Open the global login modal
            openLoginModal();
            return;
        }

        if (typeof originalOnClick === 'function') {
            originalOnClick(event);
        }

        // If authenticated, execute the original handler
        if (onAuth) {
            onAuth(event);
        }
    };

    // If user is not authenticated and fallback is provided
    if (!auth.user && fallback) {
        return fallback;
    }

    // Attach the gate to the child control instead of relying on a non-semantic
    // wrapper with a click handler.
    return (
        Children.map(children, (child) => {
            if (!isValidElement(child)) {
                return child;
            }

            return cloneElement(child, {
                onClick: (event) => handleClick(event, child.props.onClick),
            });
        })
    );
}
