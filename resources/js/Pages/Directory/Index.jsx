import { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    Filter,
    Search,
    X,
} from 'lucide-react';

import TenantCard from '@/Components/Public/TenantCard';
import PublicLayout from '@/Layouts/PublicLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
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

const ALL_VALUE = 'all';

function cleanParams(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== '')
    );
}

function getPaginatorItems(tenants) {
    if (Array.isArray(tenants?.data)) {
        return tenants.data;
    }

    if (Array.isArray(tenants)) {
        return tenants;
    }

    return [];
}

function getResultCount(tenants, items) {
    return tenants?.total ?? tenants?.meta?.total ?? items.length;
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
                    Categoria
                </label>
                <Select
                    value={filters.category || ALL_VALUE}
                    onValueChange={(value) => onFilterChange('category', value === ALL_VALUE ? '' : value)}
                >
                    <SelectTrigger id="directory-category-filter" data-testid="directory-filter-control">
                        <SelectValue placeholder="Todas las categorias" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value={ALL_VALUE}>Todas las categorias</SelectItem>
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
                <X className="mr-2 h-4 w-4" aria-hidden="true" />
                Limpiar filtros
            </Button>
        </div>
    );
}

function ActiveFilters({ categories, cities, filters, onFilterChange }) {
    const category = categories.find((item) => item.slug === filters.category);
    const city = cities.find((item) => item.slug === filters.city);
    const active = [
        filters.search ? { key: 'search', label: `Busqueda: ${filters.search}` } : null,
        city ? { key: 'city', label: `Ciudad: ${city.name}` } : null,
        category ? { key: 'category', label: `Categoria: ${category.name}` } : null,
    ].filter(Boolean);

    if (!active.length) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {active.map((item) => (
                <Badge key={item.key} variant="secondary" className="gap-2">
                    {item.label}
                    <button
                        type="button"
                        onClick={() => onFilterChange(item.key, '')}
                        className="rounded-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        aria-label={`Quitar filtro ${item.label}`}
                    >
                        <X className="h-3 w-3" aria-hidden="true" />
                    </button>
                </Badge>
            ))}
        </div>
    );
}

function EmptyState({ filters, onClear }) {
    return (
        <Card className="border-dashed" data-testid="directory-empty-state">
            <CardContent className="flex flex-col items-center gap-5 py-16 text-center">
                <span className="grid h-16 w-16 place-items-center rounded-md bg-muted text-muted-foreground">
                    <Search className="h-7 w-7" aria-hidden="true" />
                </span>
                <div className="max-w-md">
                    <h2 className="text-xl font-semibold">
                        {filters.search ? `No encontramos "${filters.search}"` : 'No hay negocios disponibles'}
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Prueba con otra busqueda, cambia ciudad o limpia los filtros para ver mas negocios.
                    </p>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row">
                    <Button variant="outline" onClick={onClear}>
                        <X className="mr-2 h-4 w-4" aria-hidden="true" />
                        Limpiar filtros
                    </Button>
                    <Button asChild>
                        <Link href={route('tenant.register')}>
                            <Building2 className="mr-2 h-4 w-4" aria-hidden="true" />
                            Registrar mi negocio
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function Pagination({ links }) {
    if (!links?.length) {
        return null;
    }

    return (
        <nav className="mt-10 flex flex-wrap justify-center gap-2" aria-label="Paginacion del directorio">
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
            route('directory.index'),
            cleanParams(nextFilters),
            { preserveState: true, preserveScroll: true, replace: true }
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
            <Head title={`${pageTitle} - Conecta HN`} />

            <section className="border-b bg-muted/40" data-testid="directory-page">
                <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <Badge variant="outline">Directorio</Badge>
                            <h1 className="mt-4 text-3xl font-semibold tracking-normal sm:text-4xl">
                                {pageTitle}
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">
                                Explora negocios locales por ciudad, categoria o busqueda directa.
                            </p>
                        </div>
                        <Badge variant="secondary" className="w-fit">
                            {resultCount} resultados
                        </Badge>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid gap-8 lg:grid-cols-[18rem_1fr]">
                    <aside className="hidden lg:block">
                        <Card className="sticky top-24">
                            <CardHeader>
                                <CardTitle className="text-lg">Filtros</CardTitle>
                                <CardDescription>Ajusta la vista del directorio.</CardDescription>
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
                    </aside>

                    <div className="min-w-0">
                        <div className="flex flex-col gap-4">
                            <div className="flex gap-3">
                                <FieldGroup className="flex-1 gap-0">
                                    <Field>
                                        <FieldLabel htmlFor="directory-search" className="sr-only">
                                            Buscar negocios
                                        </FieldLabel>
                                        <div className="relative">
                                            <Search
                                                className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <Input
                                                id="directory-search"
                                                type="search"
                                                value={searchTerm}
                                                onChange={(event) => setSearchTerm(event.target.value)}
                                                placeholder="Buscar negocios, productos o servicios..."
                                                className="pl-9"
                                                data-testid="directory-search-control"
                                            />
                                        </div>
                                    </Field>
                                </FieldGroup>

                                <Sheet open={mobileFiltersOpen} onOpenChange={setMobileFiltersOpen}>
                                    <SheetTrigger asChild>
                                        <Button variant="outline" className="lg:hidden">
                                            <Filter className="mr-2 h-4 w-4" aria-hidden="true" />
                                            Filtros
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="right">
                                        <SheetHeader>
                                            <SheetTitle>Filtros del directorio</SheetTitle>
                                            <SheetDescription>
                                                Ajusta la busqueda, categoria o ciudad para encontrar negocios disponibles.
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
                                        <TenantCard key={tenant.id || tenant.slug} tenant={tenant} />
                                    ))}
                                </div>
                                <Pagination links={tenants.links} />
                            </>
                        ) : (
                            <EmptyState
                                filters={filters}
                                onClear={() => handleFilterChange('clear', null)}
                            />
                        )}
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
