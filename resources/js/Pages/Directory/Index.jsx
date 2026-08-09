import { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Bot,
    Building2,
    Filter,
    MapPin,
    Send,
    Search,
    Store,
    X,
} from 'lucide-react';

import PublicLayout from '@/Layouts/PublicLayout';
import EmptyState from '@/Components/Marketing/EmptyState';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';

const ALL_VALUE = 'all';

function cleanParams(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== '')
    );
}

function getTenantHref(tenant) {
    if (!tenant?.slug) {
        return '/directorio';
    }

    return `/t/${encodeURIComponent(tenant.slug)}`;
}

function getStorageUrl(path) {
    if (!path) {
        return null;
    }

    if (path.startsWith('http') || path.startsWith('/')) {
        return path;
    }

    return `/storage/${path}`;
}

function getPaginatorItems(tenants) {
    if (Array.isArray(tenants?.data)) {
        return tenants.data.map((item) => item?.data ?? item);
    }

    if (Array.isArray(tenants)) {
        return tenants.map((item) => item?.data ?? item);
    }

    return [];
}

function getResultCount(tenants, items) {
    return tenants?.total ?? tenants?.meta?.total ?? items.length;
}

function DirectoryCard({ tenant }) {
    const imageUrl = getStorageUrl(tenant.banner_path || tenant.cover_url || tenant.logo_url);
    const cityName = typeof tenant.city === 'string' ? tenant.city : tenant.city?.name;
    const categoryName = typeof tenant.category === 'string' ? tenant.category : tenant.category?.name;

    return (
        <Card className="flex h-full flex-col overflow-hidden transition-shadow motion-reduce:transition-none hover:shadow-md" data-testid="tenant-card">
            <Link href={getTenantHref(tenant)} className="flex h-full flex-col" data-testid="primary-tenant-cta">
                <div className="grid aspect-video place-items-center bg-muted">
                    {imageUrl ? (
                        <img
                            src={imageUrl}
                            alt={`Imagen de ${tenant.name}`}
                            loading="lazy"
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <Store className="size-10 text-muted-foreground" aria-hidden="true" />
                    )}
                </div>

                <CardHeader className="gap-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <CardTitle className="truncate text-lg leading-6">
                                {tenant.name}
                            </CardTitle>
                            <CardDescription className="mt-1 truncate">
                                {categoryName || 'Comercio local'}
                            </CardDescription>
                        </div>

                        <Badge variant="outline" className="shrink-0">
                            Verificado
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent className="flex flex-1 flex-col gap-4">
                    <p className="line-clamp-2 text-sm leading-6 text-muted-foreground">
                        {tenant.description || 'Descubre lo que este negocio tiene para ti.'}
                    </p>

                    {tenant.tags?.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {tenant.tags.slice(0, 3).map((tag) => (
                                <Badge key={tag} variant="outline">
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                    )}
                </CardContent>

                <CardFooter className="mt-auto justify-between gap-3">
                    <span className="flex min-w-0 items-center gap-2 text-sm text-muted-foreground">
                        <MapPin className="size-4 shrink-0" aria-hidden="true" />
                        <span className="truncate">{cityName || 'Honduras'}</span>
                    </span>
                    <ArrowRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                </CardFooter>
            </Link>
        </Card>
    );
}

function FilterControls({ categories, cities, filters, onFilterChange }) {
    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-2">
                <label htmlFor="directory-city-filter" className="text-sm font-medium">
                    Ciudad
                </label>
                <Select
                    value={filters.city || ALL_VALUE}
                    onValueChange={(value) => onFilterChange('city', value === ALL_VALUE ? '' : value)}
                >
                    <SelectTrigger id="directory-city-filter" data-testid="directory-filter-control">
                        <SelectValue placeholder="Todas las ciudades" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value={ALL_VALUE}>Todas las ciudades</SelectItem>
                            {cities.map((city) => (
                                <SelectItem key={city.id ?? city.slug} value={city.slug}>
                                    {city.name}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <div className="flex flex-col gap-2">
                <label htmlFor="directory-category-filter" className="text-sm font-medium">
                    Categoría
                </label>
                <Select
                    value={filters.category || ALL_VALUE}
                    onValueChange={(value) => onFilterChange('category', value === ALL_VALUE ? '' : value)}
                >
                    <SelectTrigger id="directory-category-filter" data-testid="directory-filter-control">
                        <SelectValue placeholder="Todas las categorías" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value={ALL_VALUE}>Todas las categorías</SelectItem>
                            {categories.map((category) => (
                                <SelectItem key={category.id ?? category.slug} value={category.slug}>
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <Separator />

            <Button variant="outline" onClick={() => onFilterChange('clear', null)}>
                <X data-icon="inline-start" aria-hidden="true" />
                Limpiar filtros
            </Button>
        </div>
    );
}

function ActiveFilters({ categories, cities, filters, onFilterChange }) {
    const category = categories.find((item) => item.slug === filters.category);
    const city = cities.find((item) => item.slug === filters.city);
    const active = [
        filters.search ? { key: 'search', label: `Búsqueda: ${filters.search}` } : null,
        city ? { key: 'city', label: `Ciudad: ${city.name}` } : null,
        category ? { key: 'category', label: `Categoría: ${category.name}` } : null,
    ].filter(Boolean);

    if (!active.length) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {active.map((item) => (
                <Badge key={item.key} variant="secondary" className="max-w-full gap-2">
                    <span className="min-w-0 truncate">{item.label}</span>
                    <button
                        type="button"
                        onClick={() => onFilterChange(item.key, '')}
                        className="rounded-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        aria-label={`Quitar filtro ${item.label}`}
                    >
                        <X className="size-3" aria-hidden="true" />
                    </button>
                </Badge>
            ))}
        </div>
    );
}


function Pagination({ links }) {
    if (!links?.length) {
        return null;
    }

    return (
        <nav className="mt-10 flex flex-wrap justify-center gap-2" aria-label="Paginación del directorio">
            {links.map((link, index) => {
                const label = link.label
                    .replace('&laquo;', 'Anterior')
                    .replace('&raquo;', 'Siguiente')
                    .replace('Previous', 'Anterior')
                    .replace('Next', 'Siguiente');

                if (!link.url) {
                    return (
                        <Button key={`${label}-${index}`} variant="outline" disabled>
                            {label}
                        </Button>
                    );
                }

                return (
                    <Button
                        key={`${label}-${index}`}
                        variant={link.active ? 'default' : 'outline'}
                        asChild
                    >
                        <Link href={link.url}>{label}</Link>
                    </Button>
                );
            })}
        </nav>
    );
}

function DirectoryGuide({ instance = 'desktop' }) {
    const inputId = `directory-guide-input-${instance}`;
    const [message, setMessage] = useState('¿Qué puedo comprar con 150 lempiras?');
    const [answer, setAnswer] = useState(null);
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const ask = async (event) => {
        event.preventDefault();

        const cleanMessage = message.trim();
        if (!cleanMessage) {
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/api/v1/directory/guide/chat', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: cleanMessage }),
            });

            if (!response.ok) {
                throw new Error('No pudimos consultar la guía.');
            }

            const payload = await response.json();
            setAnswer(payload.answer || 'No encontré una respuesta pública para esa pregunta.');
            setItems(Array.isArray(payload.items) ? payload.items : []);
        } catch {
            setAnswer(null);
            setItems([]);
            setError('La guía no respondió. El directorio sigue disponible para buscar manualmente.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card className="border-border/70 bg-foreground text-background shadow-[0_24px_80px_rgba(15,23,42,0.14)]" data-testid="directory-guide">
            <CardHeader>
                <Badge variant="secondary" className="w-fit rounded-md">
                    Guía pública con IA
                </Badge>
                <h2 className="font-display text-2xl font-semibold leading-none tracking-tight">Pregunta con datos públicos</h2>
                <CardDescription className="text-background/70">
                    Responde desde negocios, catálogo y servicios visibles. No usa ventas privadas ni datos internos.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={ask} className="flex flex-col gap-3">
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor={inputId} className="sr-only">
                                Pregunta para la guía del directorio
                            </FieldLabel>
                            <div className="flex min-w-0 gap-2 rounded-xl border border-primary-foreground/10 bg-primary-foreground/10 p-2">
                                <Input
                                    id={inputId}
                                    value={message}
                                    onChange={(event) => setMessage(event.target.value)}
                                    className="h-11 min-w-0 border-0 bg-white text-foreground placeholder:text-muted-foreground focus-visible:ring-1"
                                    placeholder="Ej. ¿qué puedo comprar con 150 lempiras?"
                                    data-testid="directory-guide-chat-input"
                                />
                                <Button type="submit" variant="secondary" size="icon" disabled={loading} aria-label="Preguntar" data-testid="directory-guide-chat-submit">
                                    {loading ? <Spinner /> : <Send aria-hidden="true" />}
                                </Button>
                            </div>
                        </Field>
                    </FieldGroup>
                </form>

                <div className="mt-5 rounded-xl border border-primary-foreground/10 bg-primary-foreground/10 p-4">
                    <div className="flex items-start gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-foreground">
                            {error ? <AlertCircle aria-hidden="true" /> : <Bot aria-hidden="true" />}
                        </span>
                        <div className="min-w-0">
                            <p
                                className="text-sm leading-6 text-background/85"
                                data-testid="directory-guide-chat-answer"
                                role={error ? 'alert' : 'status'}
                                aria-live="polite"
                            >
                                {error || answer || 'Prueba con presupuesto, ciudad o tipo de servicio. La respuesta aparece aquí.'}
                            </p>
                            {items.length ? (
                                <div className="mt-4 flex flex-col gap-2">
                                    {items.slice(0, 3).map((item) => (
                                        <Button key={`${item.business_name}-${item.name}`} variant="secondary" className="min-w-0 justify-between gap-3" asChild>
                                            <Link href={item.url || '/directorio'}>
                                                <span className="min-w-0 truncate">{item.name}</span>
                                                <span className="shrink-0 text-xs">{item.price_label}</span>
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function DirectoryIndex({
    tenants = {},
    categories = [],
    cities = [],
    activeCity = null,
    filters = {},
}) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const items = useMemo(() => getPaginatorItems(tenants), [tenants]);
    const resultCount = getResultCount(tenants, items);

    const applyFilters = useCallback((nextFilters) => {
        router.get(
            '/directorio',
            cleanParams(nextFilters),
            { preserveState: false, preserveScroll: true, replace: true }
        );
    }, []);

    const handleFilterChange = useCallback((key, value) => {
        if (key === 'clear') {
            setSearchTerm('');
            applyFilters({});
            setMobileFiltersOpen(false);
            return;
        }

        const nextFilters = {
            ...filters,
            [key]: value || null,
        };

        if (key === 'search') {
            setSearchTerm(value || '');
        }

        applyFilters(nextFilters);
        setMobileFiltersOpen(false);
    }, [applyFilters, filters]);

    useEffect(() => {
        setSearchTerm(filters.search || '');
    }, [filters.search]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            if (searchTerm !== (filters.search || '')) {
                applyFilters({
                    ...filters,
                    search: searchTerm || null,
                });
            }
        }, 400);

        return () => window.clearTimeout(timer);
    }, [applyFilters, filters, searchTerm]);

    const pageTitle = activeCity?.name
        ? `Directorio en ${activeCity.name}`
        : filters.category
            ? categories.find((category) => category.slug === filters.category)?.name || 'Directorio'
            : 'Directorio de negocios';

    return (
        <PublicLayout>
            <Head title={`${pageTitle} - Dovra AI`} />

            <section className="border-b bg-[radial-gradient(circle_at_top_right,hsl(var(--secondary))_0,transparent_32%),linear-gradient(180deg,#fff_0%,#f8fafc_100%)]" data-testid="directory-page">
                <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <Badge variant="outline" className="rounded-md bg-background/80">Directorio para clientes</Badge>
                            <h1 className="mt-4 font-display text-4xl font-bold tracking-normal sm:text-5xl">
                                {pageTitle}
                            </h1>
                            <p className="mt-4 max-w-2xl text-base leading-7 text-muted-foreground">
                                Encuentra negocios reales, guarda favoritos y entra a micrositios públicos sin ver información privada del plan del negocio.
                            </p>
                        </div>
                        <Badge variant="secondary" className="w-fit rounded-md px-4 py-2" role="status" aria-live="polite">
                            {resultCount} resultados
                        </Badge>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="grid gap-8 lg:grid-cols-[18rem_1fr]">
                    <aside className="hidden lg:block">
                        <div className="sticky top-24 flex flex-col gap-4">
                            <DirectoryGuide instance="desktop" />
                        <Card className="border-border/70 shadow-none">
                            <CardHeader>
                                <CardTitle className="text-lg">Filtros</CardTitle>
                                <CardDescription>Refina por ciudad o categoría.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FilterControls
                                    categories={categories}
                                    cities={cities}
                                    filters={filters}
                                    onFilterChange={handleFilterChange}
                                />
                            </CardContent>
                        </Card>
                        </div>
                    </aside>

                    <div className="min-w-0">
                        <div className="flex flex-col gap-4">
                            <div className="lg:hidden">
                                <DirectoryGuide instance="mobile" />
                            </div>

                            <div className="flex min-w-0 gap-3 rounded-xl border bg-background p-2 shadow-md">
                                <FieldGroup className="min-w-0 flex-1 gap-0">
                                    <Field>
                                        <FieldLabel htmlFor="directory-search" className="sr-only">
                                            Buscar negocios
                                        </FieldLabel>
                                        <div className="relative">
                                            <Search
                                                className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <Input
                                                id="directory-search"
                                                type="search"
                                                value={searchTerm}
                                                onChange={(event) => setSearchTerm(event.target.value)}
                                                placeholder="Buscar negocios, productos o servicios..."
                                                className="h-12 min-w-0 rounded-xl border-0 bg-muted/70 pl-9 shadow-none focus-visible:ring-1"
                                                data-testid="directory-search-control"
                                            />
                                        </div>
                                    </Field>
                                </FieldGroup>

                                <Sheet open={mobileFiltersOpen} onOpenChange={setMobileFiltersOpen}>
                                    <SheetTrigger asChild>
                                        <Button variant="outline" className="h-12 rounded-xl lg:hidden">
                                            <Filter data-icon="inline-start" aria-hidden="true" />
                                            Filtros
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="right">
                                        <SheetHeader>
                                            <SheetTitle>Filtros del directorio</SheetTitle>
                                            <SheetDescription>
                                                Ajusta la búsqueda, categoría o ciudad para encontrar negocios disponibles.
                                            </SheetDescription>
                                        </SheetHeader>
                                        <div className="mt-6">
                                            <FilterControls
                                                categories={categories}
                                                cities={cities}
                                                filters={filters}
                                                onFilterChange={handleFilterChange}
                                            />
                                        </div>
                                    </SheetContent>
                                </Sheet>
                            </div>

                            <ActiveFilters
                                categories={categories}
                                cities={cities}
                                filters={filters}
                                onFilterChange={handleFilterChange}
                            />
                        </div>

                        <Separator className="my-6" />

                        {items.length > 0 ? (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {items.map((tenant) => (
                                        <DirectoryCard key={tenant.id} tenant={tenant} />
                                    ))}
                                </div>
                                <Pagination links={tenants.links} />
                            </>
                        ) : (
                            <EmptyState
                                icon={Search}
                                title={filters.search ? `No encontramos "${filters.search}"` : 'No hay negocios disponibles'}
                                description="Prueba con otra búsqueda, cambia ciudad o limpia los filtros para ver más negocios."
                                action={{
                                    label: 'Limpiar filtros',
                                    onClick: () => handleFilterChange('clear', null),
                                }}
                                secondaryAction={{
                                    label: 'Registrar mi negocio',
                                    onClick: () => router.visit('/registrar-negocio'),
                                }}
                            />
                        )}
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
