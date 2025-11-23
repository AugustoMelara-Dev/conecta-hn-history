import { Link, usePage } from '@inertiajs/react';
import { Home, Search, Heart, User, PlusCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function MobileBottomNav() {
    const { url } = usePage();

    // Navigation items configuration
    const navItems = [
        {
            label: 'Inicio',
            href: '/',
            icon: Home,
            active: url === '/',
            isFab: false
        },
        {
            label: 'Explorar',
            href: '/directorio',
            icon: Search,
            active: url.startsWith('/directorio'),
            isFab: false
        },
        {
            label: 'Publicar',
            href: '/publicar',
            icon: PlusCircle,
            active: url === '/publicar',
            isFab: true // Central FAB
        },
        {
            label: 'Favoritos',
            href: '/favoritos',
            icon: Heart,
            active: url === '/favoritos',
            isFab: false
        },
        {
            label: 'Perfil',
            href: '/perfil',
            icon: User,
            active: url.startsWith('/perfil') || url.startsWith('/mi-cuenta'),
            isFab: false
        }
    ];

    return (
        <div className="grid grid-cols-5 h-16 items-center justify-items-center px-2 pb-safe">
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
