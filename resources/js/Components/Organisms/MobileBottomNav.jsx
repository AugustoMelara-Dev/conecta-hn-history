import { Link, usePage } from '@inertiajs/react';
import { Home, Search, Heart, User, PlusCircle, Store } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * MobileBottomNav - App-wide navigation for discovery mode
 * 
 * SECURITY: FAB (Publicar) only visible to authenticated tenant owners
 * This prevents confusion for anonymous users and customers
 */
export default function MobileBottomNav() {
    const { url, props } = usePage();
    const auth = props.auth || {};
    const user = auth.user;

    // ═══════════════════════════════════════════════════════════════════════
    // FAB VISIBILITY LOGIC: Only show to authenticated users with tenants
    // ═══════════════════════════════════════════════════════════════════════
    const isAuthenticated = !!user;
    const hasTenant = !!user?.tenant_id || !!user?.tenant;
    const showFAB = isAuthenticated && hasTenant;

    // Base navigation items (always visible)
    const baseNavItems = [
        {
            label: 'Inicio',
            href: '/',
            icon: Home,
            active: url === '/',
        },
        {
            label: 'Explorar',
            href: '/directorio',
            icon: Search,
            active: url.startsWith('/directorio'),
        },
    ];

    // FAB item (conditionally added)
    const fabItem = {
        label: 'Publicar',
        href: '/admin/products/create',
        icon: PlusCircle,
        active: false,
        isFab: true,
    };

    // Post-FAB items
    const endNavItems = [
        {
            label: 'Favoritos',
            href: '/favoritos',
            icon: Heart,
            active: url === '/favoritos',
        },
        {
            label: isAuthenticated ? 'Mi Tienda' : 'Perfil',
            href: isAuthenticated ? (hasTenant ? '/admin' : '/registrar-negocio') : '/login',
            icon: isAuthenticated ? Store : User,
            active: url.startsWith('/perfil') || url.startsWith('/mi-cuenta') || url.startsWith('/admin'),
        },
    ];

    // Build final nav items based on auth state
    const navItems = showFAB
        ? [...baseNavItems, fabItem, ...endNavItems]
        : [...baseNavItems, ...endNavItems];

    // Grid columns: 5 with FAB, 4 without
    const gridCols = showFAB ? 'grid-cols-5' : 'grid-cols-4';

    return (
        <div className={cn("grid h-16 items-center justify-items-center px-2 pb-safe", gridCols)}>
            {navItems.map((item) => (
                <Link
                    key={item.label}
                    href={item.href}
                    preserveScroll={item.href === '/perfil'}
                    className={cn(
                        "flex flex-col items-center justify-center w-full h-full gap-1 transition-all duration-200 active:scale-95 touch-manipulation",
                        item.active ? "text-primary" : "text-muted-foreground hover:text-foreground",
                        item.isFab && "text-primary-foreground"
                    )}
                >
                    {/* Conditional rendering for FAB vs Normal */}
                    {item.isFab ? (
                        <div className="bg-primary rounded-full p-3 -mt-8 shadow-lg shadow-primary/30 border-4 border-background">
                            <item.icon size={24} className="stroke-[2.5px]" />
                        </div>
                    ) : (
                        <>
                            <item.icon
                                size={24}
                                className={cn(
                                    "transition-all",
                                    item.active ? "fill-primary/20 stroke-[2.5px]" : "stroke-2"
                                )}
                            />
                            <span className={cn(
                                "text-[10px] font-medium",
                                item.active ? "font-semibold" : ""
                            )}>
                                {item.label}
                            </span>
                        </>
                    )}
                </Link>
            ))}
        </div>
    );
}

