import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PUBLIC_LABEL_FONT } from '@/lib/publicTypography';

/**
 * FollowButton - Optimistic UI Toggle for Social Graph
 *
 * @param {Object} props
 * @param {string} props.tenantId - UUID of the tenant
 * @param {boolean} props.isFollowing - Initial follow state
 * @param {number} props.followersCount - Initial count
 * @param {boolean} props.showCount - Whether to display the badge
 */
export default function FollowButton({
    tenantId,
    isFollowing: initialFollowing = false,
    followersCount: initialCount = 0,
    showCount = true,
    className = '',
}) {
    const [isFollowing, setIsFollowing] = useState(initialFollowing);
    const [count, setCount] = useState(initialCount);
    const { auth } = usePage().props;

    useEffect(() => {
        setIsFollowing(initialFollowing);
        setCount(initialCount);
    }, [initialFollowing, initialCount]);

    const toggleFollow = () => {
        if (!auth?.user) {
            router.visit(route('login'), {
                data: { redirect: window.location.pathname },
            });
            return;
        }

        const newState = !isFollowing;
        setIsFollowing(newState);
        setCount((prev) => (newState ? prev + 1 : Math.max(0, prev - 1)));

        router.post(
            route('follows.toggle'),
            { tenant_id: tenantId },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    setIsFollowing(!newState);
                    setCount((prev) => (!newState ? prev + 1 : Math.max(0, prev - 1)));
                },
            }
        );
    };

    return (
        <button
            onClick={toggleFollow}
            type="button"
            className={`flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors border focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-black ${
                isFollowing
                    ? 'bg-white text-black border-black hover:bg-gray-50'
                    : 'bg-black text-white border-transparent hover:bg-gray-900'
                    } ${className}`}
            style={{ fontFamily: PUBLIC_LABEL_FONT }}
        >
            <span>{isFollowing ? 'Siguiendo' : 'Seguir'}</span>

            {showCount && (
                <span
                    className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                        isFollowing ? 'bg-gray-100 text-black' : 'bg-gray-800 text-white'
                    }`}
                >
                    {count}
                </span>
            )}
        </button>
    );
}
