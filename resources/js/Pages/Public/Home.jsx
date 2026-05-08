import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    HeartPulse,
    MapPin,
    Search,
    ShoppingBag,
    Sparkles,
    Store,
    Utensils,
    Wrench,
} from 'lucide-react';

import TenantCard from '@/Components/Public/TenantCard';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
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
import PublicLayout from '@/Layouts/PublicLayout';

const fallbackCategories = [
    { slug: 'comida', name: 'Comida', icon: Utensils },
    { slug: 'salud', name: 'Salud', icon: HeartPulse },
    { slug: 'servicios', name: 'Servicios', icon: Wrench },
    { slug: 'tiendas', name: 'Tiendas', icon: ShoppingBag },
];

const directoryPath = '/directorio';

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

function formatCount(value, fallback = 0) {
    const number = Number(value ?? fallback);

    if (!Number.isFinite(number)) {
        return String(fallback);
    }

    return new Intl.NumberFormat('es-HN').format(number);
}

function HeroSection({ stats }) {
    const [searchQuery, setSearchQuery] = useState('');
    const tenantCount = formatCount(stats?.total_tenants);
    const cityCount = formatCount(stats?.total_cities);

    const handleSearch = (event) => {
        event.preventDefault();

        const search = searchQuery.trim();
        router.get(
            directoryPath,
            search ? { search } : {},
            { preserveScroll: true }
        );
    };

    return (
        <section className="border-b bg-muted/30" data-testid="explore-page">
            <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8 lg:py-20">
                <div className="flex flex-col justify-center gap-8">
                    <div className="flex flex-col gap-5">
                        <Badge className="w-fit" variant="secondary">
                            Directorio publico de Conecta HN
                        </Badge>

                        <div className="flex flex-col gap-4">
                            <h1 className="max-w-3xl text-4xl font-semibold leading-tight tracking-normal text-foreground sm:text-5xl">
                                Encuentra negocios locales en Honduras sin perder tiempo.
                            </h1>
                            <p className="max-w-2xl text-lg leading-8 text-muted-foreground">
                                Explora servicios, tiendas y lugares de confianza. Busca por nombre, categoria o ciudad y abre el micrositio del negocio que te interesa.
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleSearch} role="search" data-testid="explore-search">
                        <FieldGroup className="gap-3">
                            <Field>
                                <FieldLabel htmlFor="explore-search-input">
                                    Buscar negocio o servicio
                                </FieldLabel>
                                <div className="flex flex-col gap-3 rounded-lg border bg-card p-3 shadow-sm sm:flex-row">
                                    <div className="relative flex-1">
                                        <Search
                                            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="explore-search-input"
                                            value={searchQuery}
                                            onChange={(event) => setSearchQuery(event.target.value)}
                                            placeholder="Ej. restaurante, mecanico, dentista"
                                            className="pl-9"
                                            data-testid="explore-search-control"
                                        />
                                    </div>
                                    <Button type="submit">
                                        <Search data-icon="inline-start" aria-hidden="true" />
                                        Buscar
                                    </Button>
                                </div>
                            </Field>
                        </FieldGroup>
                    </form>
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle className="text-lg">Marketplace activo</CardTitle>
                        <CardDescription>
                            Datos publicos disponibles para explorar negocios registrados.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="rounded-lg border bg-background p-4">
                                <p className="text-2xl font-semibold">{tenantCount}</p>
                                <p className="text-sm text-muted-foreground">Negocios</p>
                            </div>
                            <div className="rounded-lg border bg-background p-4">
                                <p className="text-2xl font-semibold">{cityCount}</p>
                                <p className="text-sm text-muted-foreground">Ciudades</p>
                            </div>
                        </div>

                        <Separator />

                        <div className="flex items-start gap-3 text-sm text-muted-foreground">
                            <MapPin className="mt-0.5 text-primary" aria-hidden="true" />
                            <p>
                                Usa filtros del directorio para comparar ubicacion, categoria y disponibilidad.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

function CategoryPills({ categories }) {
    const items = useMemo(() => {
        if (Array.isArray(categories) && categories.length > 0) {
            return categories.map((category) => ({
                ...category,
                icon: Building2,
            }));
        }

        return fallbackCategories;
    }, [categories]);

    return (
        <section className="bg-background py-12">
            <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Badge variant="outline">Categorias</Badge>
                        <h2 className="mt-3 text-2xl font-semibold tracking-normal text-foreground">
                            Explora por tipo de negocio
                        </h2>
                    </div>
                    <Button variant="ghost" asChild>
                        <Link href={directoryPath}>
                            Ver directorio
                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {items.map((category) => {
                        const Icon = category.icon || Building2;

                        return (
                            <Button
                                key={category.slug || category.name}
                                variant="outline"
                                className="h-auto justify-start gap-3 p-4"
                                asChild
                            >
                                <Link
                                    href={`${directoryPath}?category=${encodeURIComponent(category.slug)}`}
                                    data-testid="explore-category"
                                >
                                    <Icon aria-hidden="true" />
                                    <span className="flex min-w-0 flex-col items-start gap-1">
                                        <span className="truncate font-medium">{category.name}</span>
                                        {category.tenants_count ? (
                                            <span className="text-xs text-muted-foreground">
                                                {formatCount(category.tenants_count)} negocios
                                            </span>
                                        ) : null}
                                    </span>
                                </Link>
                            </Button>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

function FeaturedSection({ tenants }) {
    return (
        <section className="bg-muted/30 py-14">
            <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Badge variant="secondary">Destacados</Badge>
                        <h2 className="mt-3 text-2xl font-semibold tracking-normal text-foreground">
                            Negocios para descubrir
                        </h2>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Una seleccion de negocios visibles en el marketplace publico.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={directoryPath}>
                            Ver todos
                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                {tenants?.length ? (
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {tenants.map((tenant) => (
                            <TenantCard key={tenant.id || tenant.slug} tenant={tenant} />
                        ))}
                    </div>
                ) : (
                    <Alert data-testid="explore-empty-state">
                        <Sparkles aria-hidden="true" />
                        <AlertTitle>No hay negocios destacados todavia</AlertTitle>
                        <AlertDescription>
                            Puedes abrir el directorio completo o registrar un negocio para aparecer en el marketplace.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </section>
    );
}

function CtaSection() {
    return (
        <section className="bg-background py-14">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Card>
                    <CardContent className="flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex max-w-2xl flex-col gap-2">
                            <Badge className="w-fit" variant="outline">
                                Para negocios
                            </Badge>
                            <h2 className="text-2xl font-semibold tracking-normal text-foreground">
                                Publica tu negocio y comparte tu micrositio.
                            </h2>
                            <p className="text-sm leading-6 text-muted-foreground">
                                Crea tu perfil, muestra informacion clave y facilita que clientes te encuentren desde el directorio.
                            </p>
                        </div>
                        <Button asChild>
                            <Link
                                href={safeRoute('tenant.register', '/registrar-negocio')}
                                data-testid="explore-register-cta"
                            >
                                Registrar negocio
                                <ArrowRight data-icon="inline-end" aria-hidden="true" />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

export default function Home({ featuredTenants = [], categories = [], stats = {} }) {
    return (
        <PublicLayout>
            <Head>
                <title>Explorar negocios en Honduras - Conecta HN</title>
                <meta
                    name="description"
                    content="Explora negocios locales en Honduras por categoria, ciudad o nombre. Encuentra micrositios publicos y formas de contacto reales."
                />
                <meta
                    name="keywords"
                    content="negocios Honduras, directorio empresas, restaurantes Honduras, servicios locales"
                />
                <link rel="canonical" href={safeRoute('public.explore', '/explore')} />
            </Head>

            <HeroSection stats={stats} />
            <CategoryPills categories={categories} />
            <FeaturedSection tenants={featuredTenants} />
            <CtaSection />
        </PublicLayout>
    );
}
