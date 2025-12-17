import { Link, usePage } from '@inertiajs/react';
import { Home, Search, Heart, User, Store, Eye } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function MobileBottomNav() {
    const { auth } = usePage().props;
    const user = auth.user;

    const navItems = [
        {
            label: 'Inicio',
            icon: Home,
            href: route('directory.index'),
            active: route().current('directory.index'),
            show: true
        },
        {
            label: 'Explorar',
            icon: Search,
            href: route('directory.index'), // Could be a search page or category page
            active: false,
            show: !user
        },
        {
            label: 'Favoritos',
            icon: Heart,
            href: '#', // TODO: Implement favorites route
            active: false,
            show: user && user.role === 'buyer'
        },
        {
            label: 'Mi Negocio',
            icon: Store,
            href: '/app', // Native link for hard reload
            active: false,
            show: user && user.role === 'seller',
            native: true
        },
        {
            label: 'Vista Previa',
            icon: Eye,
            href: user && user.tenant ? route('tenant.show', user.tenant.slug) : '#',
            active: route().current('tenant.show'),
            show: user && user.role === 'seller' && user.tenant
        },
        {
            label: 'Perfil',
            icon: User,
            href: route('mi-cuenta.edit'),
            active: route().current('mi-cuenta.edit'),
            show: !!user
        },
        {
            label: 'Ingresar',
            icon: User,
            href: route('login'),
            active: route().current('login'),
            show: !user
        }
    ];

    return (
        <div className="fixed bottom-0 left-0 right-0 z-50 bg-background border-t border-border md:hidden safe-area-bottom">
            <div className="flex items-center justify-around h-16 px-2">
                {navItems.filter(item => item.show).map((item, index) => {
                    const Icon = item.icon;

                    if (item.native) {
                        return (
                            <a
                                key={index}
                                href={item.href}
                                className={cn(
                                    "flex flex-col items-center justify-center w-full h-full space-y-1",
                                    item.active ? "text-primary" : "text-muted-foreground hover:text-foreground"
                                )}
                            >
                                <Icon className="w-5 h-5" />
                                <span className="text-[10px] font-medium">{item.label}</span>
                            </a>
                        );
                    }

                    return (
                        <Link
                            key={index}
                            href={item.href}
                            className={cn(
                                "flex flex-col items-center justify-center w-full h-full space-y-1",
                                item.active ? "text-primary" : "text-muted-foreground hover:text-foreground"
                            )}
                        >
                            <Icon className="w-5 h-5" />
                            <span className="text-[10px] font-medium">{item.label}</span>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
