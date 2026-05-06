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

    const handleClick = (e) => {
        // If user is NOT authenticated
        if (!auth.user) {
            e.preventDefault();
            e.stopPropagation();

            // Open the global login modal
            openLoginModal();
            return;
        }

        // If authenticated, execute the original handler
        if (onAuth) {
            onAuth(e);
        }
    };

    // If user is not authenticated and fallback is provided
    if (!auth.user && fallback) {
        return fallback;
    }

    // Wrap children with click interceptor
    return (
        <div onClick={handleClick} className="contents">
            {children}
        </div>
    );
}
