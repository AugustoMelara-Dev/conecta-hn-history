import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Menu, Search, Store } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
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
            return route(name, params);
        }
    } catch {
        return fallback;
    }

    return fallback;
}

const navigationLinks = [
    { label: 'Directorio', href: () => safeRoute('directory.index', '/directorio') },
    { label: 'Explorar', href: () => safeRoute('public.explore', '/explore') },
];

function BrandLink() {
    return (
        <Link href={safeRoute('home', '/')} className="flex items-center gap-3">
            <span className="flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <Store aria-hidden="true" />
            </span>
            <span className="text-lg font-semibold tracking-normal text-foreground">
                Conecta HN
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
                    placeholder="Buscar negocios..."
                    className="pl-9"
                />
            </div>
        </form>
    );
}

function DesktopNavigation({ auth }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';

    return (
        <div className="hidden items-center gap-3 md:flex">
            {navigationLinks.map((item) => (
                <Button key={item.label} variant="ghost" asChild>
                    <Link href={item.href()}>{item.label}</Link>
                </Button>
            ))}

            <Separator orientation="vertical" className="h-8" />

            {auth?.user ? (
                <Button asChild>
                    <Link href={panelUrl}>Mi panel</Link>
                </Button>
            ) : (
                <>
                    <Button variant="ghost" asChild>
                        <Link href={safeRoute('login', '/login')}>Iniciar sesion</Link>
                    </Button>
                    <Button asChild>
                        <Link href={safeRoute('register', '/register')}>Registrarse</Link>
                    </Button>
                </>
            )}
        </div>
    );
}

function MobileNavigation({ auth }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="md:hidden"
                    aria-label="Abrir menu principal"
                >
                    <Menu aria-hidden="true" />
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="flex flex-col">
                <SheetHeader>
                    <SheetTitle>Menu principal</SheetTitle>
                    <SheetDescription>
                        Accede a la busqueda publica, enlaces principales y acciones de cuenta.
                    </SheetDescription>
                </SheetHeader>

                <SearchForm className="mt-6" inputId="public-search-mobile" />

                <nav className="mt-6 flex flex-col gap-2" aria-label="Navegacion movil">
                    {navigationLinks.map((item) => (
                        <Button key={item.label} variant="ghost" className="justify-start" asChild>
                            <Link href={item.href()}>{item.label}</Link>
                        </Button>
                    ))}
                </nav>

                <Separator className="my-4" />

                <div className="flex flex-col gap-2">
                    {auth?.user ? (
                        <Button asChild>
                            <Link href={panelUrl}>Mi panel</Link>
                        </Button>
                    ) : (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={safeRoute('login', '/login')}>Iniciar sesion</Link>
                            </Button>
                            <Button asChild>
                                <Link href={safeRoute('register', '/register')}>Registrarse</Link>
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
        <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
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
        <footer className="mt-auto border-t bg-card text-card-foreground">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-4 lg:px-8">
                <div className="md:col-span-2">
                    <BrandLink />
                    <p className="mt-4 max-w-md text-sm leading-6 text-muted-foreground">
                        Directorio de negocios en Honduras para descubrir servicios locales, comparar opciones y conectar con comercios de confianza.
                    </p>
                </div>

                <div>
                    <h2 className="text-sm font-semibold">Explorar</h2>
                    <ul className="mt-4 flex flex-col gap-3 text-sm text-muted-foreground">
                        <li>
                            <Link className="hover:text-foreground" href={safeRoute('directory.index', '/directorio')}>
                                Directorio
                            </Link>
                        </li>
                        <li>
                            <Link className="hover:text-foreground" href={safeRoute('public.explore', '/explore')}>
                                Negocios destacados
                            </Link>
                        </li>
                    </ul>
                </div>

                <div>
                    <h2 className="text-sm font-semibold">Legal</h2>
                    <ul className="mt-4 flex flex-col gap-3 text-sm text-muted-foreground">
                        <li>
                            <Link className="hover:text-foreground" href="/privacy">
                                Privacidad
                            </Link>
                        </li>
                        <li>
                            <Link className="hover:text-foreground" href="/terms">
                                Terminos
                            </Link>
                        </li>
                        <li>
                            <Link className="hover:text-foreground" href="/contact">
                                Contacto
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div className="border-t px-4 py-5 text-center text-xs text-muted-foreground">
                &copy; {new Date().getFullYear()} Conecta HN. Todos los derechos reservados.
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
            <Navbar />
            <main className="flex-1">{children}</main>
            <Footer />
        </div>
    );
}
