import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Home, Search, Grid, User } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function BottomNav() {
    const { url } = usePage();

    const navItems = [
        { name: 'Inicio', href: '/directorio', icon: Home },
        { name: 'Buscar', href: '/directorio/buscar', icon: Search },
        { name: 'Categorías', href: '/directorio/categorias', icon: Grid },
        { name: 'Mi Cuenta', href: '/dashboard', icon: User },
    ];

    return (
        <div className="fixed bottom-0 left-0 right-0 z-50 bg-background/80 backdrop-blur-lg border-t border-border md:hidden">
            <div className="flex justify-around items-center h-16">
                {navItems.map((item) => {
                    const isActive = url.startsWith(item.href) && (item.href !== '/directorio' || url === '/directorio');
                    return (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={cn(
                                "flex flex-col items-center justify-center w-full h-full space-y-1",
                                isActive ? "text-primary" : "text-muted-foreground hover:text-foreground"
                            )}
                        >
                            <item.icon className={cn("h-5 w-5", isActive && "fill-current")} />
                            <span className="text-[10px] font-medium">{item.name}</span>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
