import { Link, usePage } from '@inertiajs/react';
import * as Icons from 'lucide-react';
import { Search, Bell, Menu } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import useScrollDirection from '@/Hooks/useScrollDirection';
import { cn } from '@/lib/utils';
import LocationPicker from '@/Components/Organisms/LocationPicker';
import LiveSearch from '@/Components/Organisms/LiveSearch';

export default function SmartNavbar() {
    const { auth } = usePage().props;
    const scrollDirection = useScrollDirection(10);

    return (
        <nav
            className={cn(
                "sticky top-0 z-nav w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 transition-transform duration-300 h-16",
                scrollDirection === 'down' ? '-translate-y-full' : 'translate-y-0'
            )}
        >
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-full">
                <div className="flex h-full items-center justify-between gap-4">
                    {/* Logo */}
                    <Link href="/" className="flex items-center gap-2 shrink-0">
                        <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                            <span className="text-primary-foreground font-bold text-sm">C</span>
                        </div>
                        <span className="font-bold text-lg tracking-tight hidden sm:inline">
                            Conecta HN
                        </span>
                    </Link>

                    {/* Search Bar & Location - Hidden on mobile */}
                    <div className="hidden md:flex flex-1 max-w-2xl items-center gap-2">
                        {/* Location Picker */}
                        <div className="shrink-0">
                            <LocationPicker />
                        </div>

                        <div className="flex-1">
                            <LiveSearch />
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-2">
                        {auth.user ? (
                            <div className="flex items-center gap-2">
                                {/* Dropdown Menu Replacement */}
                                <div className="relative group">
                                    <button className="flex items-center gap-2 p-2 rounded-full hover:bg-muted/50 transition-colors focus:outline-none">
                                        <div className="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                            <span className="text-primary text-sm font-semibold">
                                                {auth.user.name.charAt(0).toUpperCase()}
                                            </span>
                                        </div>
                                        <span className="hidden md:inline text-sm font-medium">{auth.user.name}</span>
                                    </button>

                                    <div className="absolute right-0 mt-2 w-56 bg-popover border border-border rounded-xl shadow-xl py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 transform origin-top-right">
                                        <div className="px-4 py-3 border-b border-border bg-muted/30">
                                            <p className="text-sm font-semibold text-foreground truncate">{auth.user.name}</p>
                                            <p className="text-xs text-muted-foreground truncate">{auth.user.email}</p>
                                        </div>

                                        <div className="py-1">
                                            {/* Super Admin & Admin Links */}
                                            {(auth.user.role === 'admin' || auth.user.role === 'super_admin') && (
                                                <>
                                                    <a href="/admin" className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-primary hover:bg-primary/5 transition-colors">
                                                        <Icons.LayoutDashboard className="w-4 h-4" />
                                                        Panel Maestro
                                                    </a>
                                                    <Link href={route('directory.index')} className="flex items-center gap-2 px-4 py-2.5 text-sm text-foreground hover:bg-muted transition-colors">
                                                        <Icons.Globe className="w-4 h-4 text-muted-foreground" />
                                                        Modo Usuario
                                                    </Link>
                                                </>
                                            )}

                                            {/* Seller Links */}
                                            {auth.user.role === 'seller' && (
                                                <>
                                                    <a href="/admin" className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-primary hover:bg-primary/5 transition-colors">
                                                        <Icons.Store className="w-4 h-4" />
                                                        Mi Negocio
                                                    </a>
                                                    <Link href={route('mi-cuenta.edit')} className="flex items-center gap-2 px-4 py-2.5 text-sm text-foreground hover:bg-muted transition-colors">
                                                        <Icons.User className="w-4 h-4 text-muted-foreground" />
                                                        Perfil Comprador
                                                    </Link>
                                                </>
                                            )}

                                            {/* Buyer / Standard User Links */}
                                            {auth.user.role === 'buyer' && (
                                                <Link href={route('mi-cuenta.edit')} className="flex items-center gap-2 px-4 py-2.5 text-sm text-foreground hover:bg-muted transition-colors">
                                                    <Icons.User className="w-4 h-4 text-muted-foreground" />
                                                    Mi Cuenta
                                                </Link>
                                            )}
                                        </div>

                                        <div className="border-t border-border my-1"></div>

                                        <Link href={route('logout')} method="post" as="button" className="flex items-center gap-2 w-full text-left px-4 py-2.5 text-sm text-destructive hover:bg-destructive/10 transition-colors">
                                            <Icons.LogOut className="w-4 h-4" />
                                            Cerrar Sesión
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="flex items-center gap-2">
                                <Link href={route('login')}>
                                    <Button variant="ghost">Ingresar</Button>
                                </Link>
                                <Link href={route('register')}>
                                    <Button>Registrarse</Button>
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </nav>
    );
}
