import { Link, usePage } from '@inertiajs/react';
import * as Icons from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import useScrollDirection from '@/Hooks/useScrollDirection';
import { cn } from '@/lib/utils';
import LocationPicker from '@/Components/Organisms/LocationPicker';
import LiveSearch from '@/Components/Organisms/LiveSearch';

export default function SmartNavbar() {
    const { auth } = usePage().props;
    const scrollDirection = useScrollDirection(10);
    const user = auth?.user ?? null;
    const userName = user?.name ?? 'Usuario';
    const userEmail = user?.email ?? '';
    const userInitial = userName.charAt(0).toUpperCase();

    return (
        <nav
            className={cn(
                'sticky top-0 z-nav h-16 w-full border-b border-border/40 bg-background/95 backdrop-blur transition-transform duration-300 supports-[backdrop-filter]:bg-background/60',
                scrollDirection === 'down' ? '-translate-y-full' : 'translate-y-0'
            )}
        >
            <div className="mx-auto h-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex h-full items-center justify-between gap-4">
                    <Link href="/" className="flex shrink-0 items-center gap-2 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-primary">
                            <span className="text-sm font-bold text-primary-foreground">C</span>
                        </div>
                        <span className="hidden text-lg font-bold tracking-tight sm:inline">
                            Vito Business OS
                        </span>
                    </Link>

                    <div className="mr-2 flex flex-1 justify-end md:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="outline" size="sm" className="gap-2 rounded-full border-border bg-background/50 backdrop-blur-sm">
                                    <Icons.SlidersHorizontal className="size-4" aria-hidden="true" />
                                    <span className="text-xs font-medium">Filtros</span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="top" className="h-[80vh] w-full rounded-b-lg">
                                <SheetHeader className="mb-4">
                                    <SheetTitle>Explorar Vito Business OS</SheetTitle>
                                </SheetHeader>
                                <div className="flex flex-col gap-6">
                                    <div className="flex flex-col gap-2">
                                        <label className="text-sm font-medium">Ubicación</label>
                                        <LocationPicker />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <label className="text-sm font-medium">Búsqueda</label>
                                        <LiveSearch />
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <div className="hidden max-w-2xl flex-1 items-center gap-2 md:flex">
                        <div className="shrink-0">
                            <LocationPicker />
                        </div>

                        <div className="flex-1">
                            <LiveSearch />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="gap-2 rounded-full px-2"
                                        aria-label={`Abrir menú de ${userName}`}
                                    >
                                        <span className="flex size-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                            {userInitial}
                                        </span>
                                        <span className="hidden max-w-40 truncate text-sm font-medium md:inline">{userName}</span>
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end" className="w-64">
                                    <DropdownMenuLabel>
                                        <span className="block truncate text-sm font-semibold text-foreground">{userName}</span>
                                        <span className="block truncate text-xs font-normal text-muted-foreground">{userEmail}</span>
                                    </DropdownMenuLabel>

                                    <DropdownMenuSeparator />

                                    <DropdownMenuGroup>
                                        {(user.role === 'admin' || user.role === 'super_admin') && (
                                            <>
                                                <DropdownMenuItem asChild>
                                                    <a href="/admin" data-turbo="false">
                                                        <Icons.LayoutDashboard className="mr-2 size-4" aria-hidden="true" />
                                                        Panel Maestro
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link href={route('directory.index')}>
                                                        <Icons.Globe className="mr-2 size-4" aria-hidden="true" />
                                                        Modo Usuario
                                                    </Link>
                                                </DropdownMenuItem>
                                            </>
                                        )}

                                        {user.role === 'seller' && (
                                            <>
                                                <DropdownMenuItem asChild>
                                                    <a href="/app" data-turbo="false">
                                                        <Icons.Store className="mr-2 size-4" aria-hidden="true" />
                                                        Mi Negocio
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link href={route('mi-cuenta.edit')}>
                                                        <Icons.User className="mr-2 size-4" aria-hidden="true" />
                                                        Perfil Comprador
                                                    </Link>
                                                </DropdownMenuItem>
                                            </>
                                        )}

                                        {user.role === 'buyer' && (
                                            <DropdownMenuItem asChild>
                                                <Link href={route('mi-cuenta.edit')}>
                                                    <Icons.User className="mr-2 size-4" aria-hidden="true" />
                                                    Mi Cuenta
                                                </Link>
                                            </DropdownMenuItem>
                                        )}
                                    </DropdownMenuGroup>

                                    <DropdownMenuSeparator />

                                    <DropdownMenuItem asChild className="text-destructive focus:text-destructive">
                                        <Link href={route('logout')} method="post" as="button">
                                            <Icons.LogOut className="mr-2 size-4" aria-hidden="true" />
                                            Cerrar sesión
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <div className="flex items-center gap-2">
                                <Button variant="ghost" size="sm" asChild className="hidden sm:inline-flex">
                                    <Link href={route('login')}>Ingresar</Link>
                                </Button>
                                <Button variant="ghost" size="icon" asChild className="sm:hidden">
                                    <Link href={route('login')} aria-label="Ingresar">
                                        <Icons.LogIn className="size-5" aria-hidden="true" />
                                    </Link>
                                </Button>
                                <Button size="sm" asChild>
                                    <Link href={route('tenant.register')}>Registrar negocio</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </nav>
    );
}
