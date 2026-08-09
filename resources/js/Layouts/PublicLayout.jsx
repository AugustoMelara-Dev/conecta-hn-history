import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Menu, Search, Store } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { SkipLink } from '@/Components/ui/skip-link';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';

function safeRoute(name, fallback, params) {
    try {
        if (route().has(name)) {
            return route(name, params, false);
        }
    } catch {
        return fallback;
    }

    return fallback;
}

const navigationLinks = [
    { label: 'Directorio', href: () => safeRoute('directory.index', '/directorio') },
];

function BrandLink() {
    return (
        <Link href={safeRoute('home', '/')} className="flex items-center gap-3">
            <span className="flex size-9 items-center justify-center rounded-lg border border-foreground/10 bg-foreground text-background shadow-sm">
                <Store aria-hidden="true" className="size-4" />
            </span>
            <span className="font-display text-base font-bold tracking-normal text-foreground sm:text-lg">
                Dovra AI
            </span>
        </Link>
    );
}

function SearchForm({ className = '', inputId = 'public-search' }) {
    const [searchQuery, setSearchQuery] = useState('');

    const handleSearch = (event) => {
        event.preventDefault();

        const search = searchQuery.trim();
        router.get(
            safeRoute('directory.index', '/directorio'),
            search ? { search } : {},
            { preserveScroll: true }
        );
    };

    return (
        <form onSubmit={handleSearch} className={className} role="search">
            <label htmlFor={inputId} className="sr-only">
                Buscar negocios
            </label>
            <div className="relative">
                <Search
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id={inputId}
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    placeholder="Buscar negocios, servicios o productos"
                    className="h-10 rounded-lg border-border bg-background pl-9 shadow-none"
                />
            </div>
        </form>
    );
}

function DesktopNavigation({ auth }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const hasBusinessPanel = auth?.user && panelUrl !== '/registrar-negocio';

    return (
        <div className="hidden items-center gap-3 md:flex">
            {navigationLinks.map((item) => (
                <Button key={item.label} variant="ghost" asChild>
                    <Link href={item.href()}>{item.label}</Link>
                </Button>
            ))}

            <Separator orientation="vertical" className="h-8" />

            {auth?.user ? (
                <>
                    <Button variant="ghost" asChild>
                        <Link href={safeRoute('mi-cuenta.edit', '/mi-cuenta')}>Mi cuenta</Link>
                    </Button>
                    <Button asChild>
                        <Link href={hasBusinessPanel ? panelUrl : safeRoute('tenant.register', '/registrar-negocio')}>
                            {hasBusinessPanel ? 'Panel negocio' : 'Crear negocio'}
                        </Link>
                    </Button>
                </>
            ) : (
                <>
                    <Button variant="ghost" asChild>
                        <Link href={safeRoute('login', '/login')}>Iniciar sesión</Link>
                    </Button>
                    <Button asChild>
                        <Link href={safeRoute('tenant.register', '/registrar-negocio')}>Registrar negocio</Link>
                    </Button>
                </>
            )}
        </div>
    );
}

function MobileNavigation({ auth }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const hasBusinessPanel = auth?.user && panelUrl !== '/registrar-negocio';

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="md:hidden"
                    aria-label="Abrir menú principal"
                >
                    <Menu aria-hidden="true" />
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="flex flex-col">
                <SheetHeader>
                    <SheetTitle>Menú principal</SheetTitle>
                    <SheetDescription>
                        Accede a la búsqueda pública, enlaces principales y acciones de cuenta.
                    </SheetDescription>
                </SheetHeader>

                <SearchForm className="mt-6" inputId="public-search-mobile" />

                <nav className="mt-6 flex flex-col gap-2" aria-label="Navegación móvil">
                    {navigationLinks.map((item) => (
                        <Button key={item.label} variant="ghost" className="justify-start" asChild>
                            <Link href={item.href()}>{item.label}</Link>
                        </Button>
                    ))}
                </nav>

                <Separator className="my-4" />

                <div className="flex flex-col gap-2">
                    {auth?.user ? (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={safeRoute('mi-cuenta.edit', '/mi-cuenta')}>Mi cuenta</Link>
                            </Button>
                            <Button asChild>
                                <Link href={hasBusinessPanel ? panelUrl : safeRoute('tenant.register', '/registrar-negocio')}>
                                    {hasBusinessPanel ? 'Panel negocio' : 'Crear negocio'}
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={safeRoute('login', '/login')}>Iniciar sesión</Link>
                            </Button>
                            <Button asChild>
                                <Link href={safeRoute('tenant.register', '/registrar-negocio')}>Registrar negocio</Link>
                            </Button>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function Navbar() {
    const { auth } = usePage().props;

    return (
        <header className="sticky top-0 z-40 border-b bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75">
            <nav className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <BrandLink />
                <SearchForm className="hidden w-full max-w-md md:block" inputId="public-search-desktop" />
                <DesktopNavigation auth={auth} />
                <MobileNavigation auth={auth} />
            </nav>
        </header>
    );
}

function Footer() {
    return (
        <footer className="mt-auto border-t bg-muted/40 text-card-foreground">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-4 lg:px-8">
                <div className="md:col-span-2">
                    <BrandLink />
                    <p className="mt-4 max-w-md text-sm leading-6 text-muted-foreground">
                        Plataforma para publicar tu negocio, recibir clientes y ordenar ventas, reservas y seguimiento en un solo lugar.
                    </p>
                </div>

                <div>
                    <h2 className="text-sm font-semibold">Directorio</h2>
                    <ul className="mt-4 flex flex-col gap-3 text-sm text-muted-foreground">
                        <li>
                            <Link className="hover:text-foreground" href={safeRoute('directory.index', '/directorio')}>
                                Directorio
                            </Link>
                        </li>
                    </ul>
                </div>

                <div>
                    <h2 className="text-sm font-semibold">Legal</h2>
                    <ul className="mt-4 flex flex-col gap-3 text-sm text-muted-foreground">
                        <li>
                            <Link className="hover:text-foreground" href={safeRoute('legal.privacy', '/legal/privacy')}>
                                Privacidad
                            </Link>
                        </li>
                        <li>
                            <Link className="hover:text-foreground" href={safeRoute('legal.terms', '/legal/terms')}>
                                Términos
                            </Link>
                        </li>
                        <li>
                            <Link className="hover:text-foreground" href={safeRoute('tenant.register', '/registrar-negocio')}>
                                Contacto
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div className="border-t px-4 py-5 text-center text-xs text-muted-foreground">
                &copy; {new Date().getFullYear()} Dovra AI. Todos los derechos reservados.
            </div>
        </footer>
    );
}

export default function PublicLayout({ children }) {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <SkipLink />
            <Navbar />
            <main id="main-content" tabIndex={-1} className="flex-1 outline-none">{children}</main>
            <Footer />
        </div>
    );
}
