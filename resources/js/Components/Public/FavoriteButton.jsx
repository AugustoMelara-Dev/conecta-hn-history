import { useCallback, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Heart } from 'lucide-react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

const buttonSizes = {
    sm: 'size-8',
    md: 'size-10',
    lg: 'size-12',
};

function useFavoriteToggle(tenantId, initialFavorited, initialCount = 0, onToggled) {
    const { auth } = usePage().props;
    const [isFavorited, setIsFavorited] = useState(initialFavorited);
    const [count, setCount] = useState(initialCount);
    const [isAnimating, setIsAnimating] = useState(false);

    const handleToggle = useCallback((event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!auth?.user) {
            router.visit('/login', {
                data: { redirect: window.location.pathname },
            });
            return;
        }

        const nextState = !isFavorited;
        setIsFavorited(nextState);
        setCount((current) => nextState ? current + 1 : Math.max(0, current - 1));
        setIsAnimating(true);
        window.setTimeout(() => setIsAnimating(false), 240);

        router.post(
            route('favorites.toggle', undefined, false),
            { tenant_id: tenantId },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    setIsFavorited(!nextState);
                    setCount((current) => nextState ? Math.max(0, current - 1) : current + 1);
                },
                onSuccess: () => onToggled?.(nextState),
            }
        );
    }, [auth, isFavorited, onToggled, tenantId]);

    return { count, handleToggle, isAnimating, isFavorited };
}

export default function FavoriteButton({
    tenantId,
    isFavorited: initialFavorited = false,
    count: initialCount = 0,
    size = 'md',
    showCount = false,
    onToggled,
}) {
    const { count, handleToggle, isAnimating, isFavorited } = useFavoriteToggle(
        tenantId,
        initialFavorited,
        initialCount,
        onToggled,
    );

    return (
        <span className="relative inline-flex">
            <Button
                type="button"
                size="icon"
                variant={isFavorited ? 'default' : 'secondary'}
                onClick={handleToggle}
                className={cn(
                    'rounded-full shadow-sm transition-transform',
                    buttonSizes[size] || buttonSizes.md,
                    isAnimating && 'scale-110'
                )}
                aria-pressed={isFavorited}
                aria-label={isFavorited ? 'Quitar de favoritos' : 'Agregar a favoritos'}
                data-testid="favorite-action"
            >
                <Heart
                    data-icon="inline-start"
                    fill={isFavorited ? 'currentColor' : 'none'}
                    aria-hidden="true"
                />
            </Button>

            {showCount && count > 0 ? (
                <Badge className="absolute -bottom-2 -right-2 min-w-5 justify-center px-1" variant="secondary">
                    {count > 99 ? '99+' : count}
                </Badge>
            ) : null}
        </span>
    );
}

export function FavoriteButtonInline({ tenantId, isFavorited: initialFavorited = false, onToggled }) {
    const { handleToggle, isFavorited } = useFavoriteToggle(tenantId, initialFavorited, 0, onToggled);

    return (
        <Button
            type="button"
            variant={isFavorited ? 'default' : 'outline'}
            size="sm"
            onClick={handleToggle}
            aria-pressed={isFavorited}
            data-testid="favorite-action"
        >
            <Heart
                data-icon="inline-start"
                fill={isFavorited ? 'currentColor' : 'none'}
                aria-hidden="true"
            />
            {isFavorited ? 'Guardado' : 'Guardar'}
        </Button>
    );
}
