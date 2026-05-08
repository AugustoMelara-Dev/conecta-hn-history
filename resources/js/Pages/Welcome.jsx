import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Check,
    ChevronRight,
    MapPin,
    Search,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    Store,
    Users,
} from 'lucide-react';

import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/Components/ui/accordion';
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
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import PublicLayout from '@/Layouts/PublicLayout';

const highlights = [
    {
        icon: Store,
        title: 'Micrositio publico',
        description: 'Una vitrina clara para catalogo, horarios, contacto y ubicacion.',
    },
    {
        icon: ShoppingBag,
        title: 'Venta guiada',
        description: 'Clientes encuentran productos, reservan o consultan sin perderse.',
    },
    {
        icon: BarChart3,
        title: 'Panel operativo',
        description: 'El negocio ve actividad, productos y acciones importantes desde su panel.',
    },
];

const steps = [
    'Publica tu negocio en minutos.',
    'Comparte tu micrositio y aparece en el directorio.',
    'Recibe consultas, pedidos o reservas segun tu rubro.',
];

const faqs = [
    {
        question: 'Puedo iniciar gratis?',
        answer: 'Si. El registro mantiene el flujo actual del proyecto y el backend decide el acceso real segun el plan disponible.',
    },
    {
        question: 'Esto cambia mi forma de vender?',
        answer: 'No. La landing solo presenta mejor el producto. Checkout, pagos, reservas y permisos siguen gobernados por las rutas actuales.',
    },
    {
        question: 'Puedo mostrar productos y datos del negocio?',
        answer: 'Si. La experiencia publica apunta al directorio y a los micrositios existentes para que cada negocio tenga una presencia clara.',
    },
];

function assetUrl(path) {
    if (!path) {
        return null;
    }

    if (path.startsWith('http') || path.startsWith('/')) {
        return path;
    }

    return `/storage/${path}`;
}

function sameOriginHref(href) {
    if (!href) {
        return href;
    }

    try {
        const url = new URL(href, window.location.origin);

        if (url.origin === window.location.origin || href.startsWith('http')) {
            return `${url.pathname}${url.search}${url.hash}`;
        }
    } catch {
        return href;
    }

    return href;
}

function appRoute(name, params, fallback = '/') {
    try {
        return sameOriginHref(route(name, params));
    } catch {
        return fallback;
    }
}

function tenantHref(tenant) {
    if (tenant?.url) {
        return tenant.url;
    }

    if (tenant?.microsite_url) {
        return tenant.microsite_url;
    }

    if (tenant?.slug) {
        try {
            return appRoute('tenant.microsite.show', { tenant: tenant.slug });
        } catch {
            return appRoute('directory.index', { search: tenant.name }, '/directorio');
        }
    }

    return appRoute('directory.index', undefined, '/directorio');
}

function formatCount(value) {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return new Intl.NumberFormat('es-HN').format(number);
}

function Metric({ icon: Icon, label, value }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-4">
                <span className="flex size-10 items-center justify-center rounded-md bg-secondary text-secondary-foreground">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p className="text-2xl font-semibold leading-none">{value}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{label}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function HeroSection({ auth, canRegister, heroImage, stats }) {
    const { data, setData, get } = useForm({ search: '' });
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const imageUrl = assetUrl(heroImage);
    const tenantCount = formatCount(stats?.tenants ?? stats?.total_tenants);
    const cityCount = formatCount(stats?.cities ?? stats?.total_cities);

    const handleSearch = (event) => {
        event.preventDefault();

        get(appRoute('directory.index', undefined, '/directorio'), {
            preserveScroll: true,
        });
    };

    return (
        <section className="border-b bg-background">
            <div
                className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1.08fr_0.92fr] lg:px-8 lg:py-16"
                data-testid="landing-page"
            >
                <div className="flex flex-col justify-center">
                    <Badge variant="secondary" className="mb-5 w-fit">
                        Plataforma para comercio local en Honduras
                    </Badge>

                    <h1 className="max-w-3xl text-4xl font-semibold leading-tight text-foreground sm:text-5xl lg:text-6xl">
                        Tu negocio listo para vender, reservar y ser encontrado.
                    </h1>

                    <p className="mt-5 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                        Conecta HN ordena presencia digital, directorio, micrositio y panel administrativo en una experiencia clara para clientes y negocios.
                    </p>

                    <form
                        onSubmit={handleSearch}
                        className="mt-8 max-w-2xl"
                        role="search"
                        data-testid="landing-search"
                    >
                        <label htmlFor="landing-search-input" className="sr-only">
                            Buscar negocios en el directorio
                        </label>
                        <div className="flex flex-col gap-3 rounded-lg border bg-card p-3 shadow-sm sm:flex-row">
                            <div className="relative flex-1">
                                <Search
                                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="landing-search-input"
                                    value={data.search}
                                    onChange={(event) => setData('search', event.target.value)}
                                    placeholder="Buscar restaurantes, barberias, servicios..."
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit">
                                Buscar
                                <ArrowRight data-icon="inline-end" aria-hidden="true" />
                            </Button>
                        </div>
                    </form>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        {auth?.user ? (
                            <Button size="lg" asChild data-testid="landing-primary-cta">
                                <Link href={panelUrl}>
                                    Ir a mi panel
                                    <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                {canRegister !== false && (
                                    <Button size="lg" asChild data-testid="landing-primary-cta">
                                        <Link href={appRoute('register', undefined, '/register')}>
                                            Registrar mi negocio
                                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                )}
                                <Button size="lg" variant="outline" asChild data-testid="landing-directory-cta">
                                    <Link href={appRoute('directory.index', undefined, '/directorio')}>Ver directorio</Link>
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="mt-8 grid gap-3 sm:grid-cols-2">
                        <Metric icon={Users} label="Negocios activos" value={tenantCount} />
                        <Metric icon={MapPin} label="Ciudades cubiertas" value={cityCount} />
                    </div>
                </div>

                <div className="flex min-h-[440px] flex-col gap-4">
                    <Card className="overflow-hidden">
                        <div className="relative">
                            {imageUrl ? (
                                <img
                                    src={imageUrl}
                                    alt="Negocio local atendiendo clientes en Honduras"
                                    className="h-72 w-full object-cover sm:h-96"
                                    loading="eager"
                                />
                            ) : (
                                <div className="grid h-72 place-items-center bg-muted sm:h-96">
                                    <Store className="size-16 text-muted-foreground" aria-hidden="true" />
                                </div>
                            )}
                            <div className="absolute bottom-4 left-4 right-4">
                                <Alert className="bg-background/95 shadow-sm backdrop-blur">
                                    <Sparkles className="size-4" aria-hidden="true" />
                                    <AlertTitle>Presencia clara desde el primer dia</AlertTitle>
                                    <AlertDescription>
                                        Directorio, catalogo y contacto en una misma experiencia publica.
                                    </AlertDescription>
                                </Alert>
                            </div>
                        </div>
                        <CardContent className="grid gap-3 p-4 sm:grid-cols-3">
                            {highlights.map((item) => (
                                <div key={item.title} className="rounded-md border bg-background p-3">
                                    <item.icon className="size-5 text-muted-foreground" aria-hidden="true" />
                                    <p className="mt-3 text-sm font-medium">{item.title}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>
    );
}

function ValueSection() {
    return (
        <section id="beneficios" className="bg-muted/40 py-14">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <Badge variant="outline">Beneficios</Badge>
                    <h2 className="mt-4 text-3xl font-semibold">Una landing que explica el producto sin ruido</h2>
                    <p className="mt-3 text-muted-foreground">
                        El rediseño prioriza conversion, lectura rapida, confianza y accesibilidad sin mover contratos sensibles.
                    </p>
                </div>

                <div className="mt-8 grid gap-4 md:grid-cols-3">
                    {highlights.map((item) => (
                        <Card key={item.title}>
                            <CardHeader className="gap-4">
                                <span className="flex size-10 items-center justify-center rounded-md bg-secondary text-secondary-foreground">
                                    <item.icon className="size-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <CardTitle className="text-lg">{item.title}</CardTitle>
                                    <CardDescription className="mt-2 leading-6">
                                        {item.description}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}

function CategorySection({ categories }) {
    const visibleCategories = categories?.slice(0, 8) ?? [];

    return (
        <section className="py-14">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <Badge variant="outline">Categorias</Badge>
                        <h2 className="mt-4 text-3xl font-semibold">Rutas rapidas para descubrir negocios</h2>
                        <p className="mt-3 max-w-2xl text-muted-foreground">
                            Cada categoria lleva al directorio actual para mantener la misma experiencia de busqueda.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={appRoute('directory.index', undefined, '/directorio')}>
                            Ver todo
                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                {visibleCategories.length ? (
                    <div className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {visibleCategories.map((category) => (
                            <Card key={category.id ?? category.slug} data-testid="landing-category-card">
                                <CardContent className="p-0">
                                    <Button variant="ghost" className="h-auto w-full justify-between p-4" asChild>
                                        <Link href={appRoute('directory.index', { category: category.slug }, '/directorio')}>
                                            <span className="text-left">{category.name}</span>
                                            <ChevronRight data-icon="inline-end" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Alert className="mt-8">
                        <AlertTitle>Directorio disponible</AlertTitle>
                        <AlertDescription>
                            Cuando haya categorias visibles, apareceran aqui. Mientras tanto, el directorio completo sigue disponible.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </section>
    );
}

function TenantsSection({ tenants }) {
    const visibleTenants = tenants?.slice(0, 8) ?? [];

    return (
        <section className="border-y bg-muted/40 py-14">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <Badge variant="outline">Negocios</Badge>
                        <h2 className="mt-4 text-3xl font-semibold">Comercios que el cliente puede explorar</h2>
                        <p className="mt-3 max-w-2xl text-muted-foreground">
                            La prueba social usa datos reales del backend y enlaza al micrositio canonico de cada negocio.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={appRoute('directory.index', undefined, '/directorio')}>
                            Ver directorio
                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                {visibleTenants.length ? (
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {visibleTenants.map((tenant) => {
                            const logoUrl = assetUrl(tenant.logo_url ?? tenant.logo_path);
                            const categoryName = typeof tenant.category === 'string'
                                ? tenant.category
                                : tenant.category?.name;

                            return (
                                <Card key={tenant.id} className="overflow-hidden" data-testid="landing-tenant-card">
                                    <Link href={tenantHref(tenant)} className="block">
                                        <div className="grid h-36 place-items-center bg-secondary">
                                            {logoUrl ? (
                                                <img
                                                    src={logoUrl}
                                                    alt={`Logo de ${tenant.name}`}
                                                    className="size-20 rounded-md object-cover"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <Store className="size-10 text-muted-foreground" aria-hidden="true" />
                                            )}
                                        </div>
                                        <CardHeader>
                                            <CardTitle className="truncate text-lg">{tenant.name}</CardTitle>
                                            <CardDescription>{categoryName ?? 'Comercio local'}</CardDescription>
                                        </CardHeader>
                                    </Link>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <Alert className="mt-8">
                        <AlertTitle>Negocios en preparacion</AlertTitle>
                        <AlertDescription>
                            La landing no se rompe si aun no hay negocios destacados. El CTA principal sigue llevando al registro y al directorio.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </section>
    );
}

function ProcessSection() {
    return (
        <section className="py-14">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
                <div>
                    <Badge variant="outline">Como funciona</Badge>
                    <h2 className="mt-4 text-3xl font-semibold">Simple para el negocio, claro para el cliente</h2>
                    <p className="mt-3 text-muted-foreground">
                        La experiencia publica enfoca el valor sin prometer automatizaciones que no pertenecen a la landing.
                    </p>
                </div>

                <Card>
                    <CardContent className="p-6">
                        <ol className="grid gap-4">
                            {steps.map((step, index) => (
                                <li key={step} className="flex gap-4">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
                                        {index + 1}
                                    </span>
                                    <p className="pt-1 text-sm leading-6 text-muted-foreground">{step}</p>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

function PricingSection({ canRegister }) {
    return (
        <section id="planes" className="bg-muted/40 py-14" data-testid="landing-pricing">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <Badge variant="outline">Planes</Badge>
                    <h2 className="mt-4 text-3xl font-semibold">Empieza simple y sube cuando tengas traccion</h2>
                    <p className="mt-3 text-muted-foreground">
                        La landing presenta el valor. Billing, aprobaciones y estados de plan siguen en las pantallas canonicas del producto.
                    </p>
                </div>

                <div className="mt-8 grid gap-4 md:grid-cols-[0.95fr_1.05fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Semilla</CardTitle>
                            <CardDescription>Para salir publicado y validar demanda.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">Gratis</p>
                            <Separator className="my-6" />
                            <ul className="flex flex-col gap-3 text-sm text-muted-foreground">
                                {[
                                    'Micrositio publico',
                                    'Presencia en directorio',
                                    'Panel administrativo base',
                                    'Contacto y datos del negocio',
                                ].map((item) => (
                                    <li key={item} className="flex items-center gap-2">
                                        <Check className="size-4 text-foreground" aria-hidden="true" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                            {canRegister !== false && (
                                <Button className="mt-6 w-full" asChild>
                                    <Link href={appRoute('register', undefined, '/register')}>Crear cuenta</Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <Badge className="w-fit">Para operar mejor</Badge>
                            <CardTitle>PRO</CardTitle>
                            <CardDescription>Para negocios con mas catalogo, reportes y gestion continua.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-4xl font-semibold">L 399</p>
                            <p className="mt-1 text-sm text-muted-foreground">por mes, segun disponibilidad del plan</p>
                            <Separator className="my-6" />
                            <div className="grid gap-3 sm:grid-cols-2">
                                {[
                                    'Mas productos visibles',
                                    'Reportes operativos',
                                    'Herramientas de marketing',
                                    'Mejor presencia publica',
                                ].map((item) => (
                                    <div key={item} className="flex items-center gap-2 text-sm">
                                        <ShieldCheck className="size-4" aria-hidden="true" />
                                        {item}
                                    </div>
                                ))}
                            </div>
                            <Button className="mt-6 w-full" variant="outline" asChild>
                                <a href="#beneficios">Ver beneficios</a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>
    );
}

function FaqSection() {
    return (
        <section className="py-14" data-testid="landing-faq">
            <div className="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div>
                    <Badge variant="outline">Preguntas</Badge>
                    <h2 className="mt-4 text-3xl font-semibold">Respuestas sin promesas infladas</h2>
                    <p className="mt-3 text-muted-foreground">
                        Copy claro para que la landing venda sin inventar capacidades fuera del producto actual.
                    </p>
                </div>

                <Accordion type="single" collapsible className="rounded-lg border bg-card px-4">
                    {faqs.map((item, index) => (
                        <AccordionItem key={item.question} value={`faq-${index}`}>
                            <AccordionTrigger>{item.question}</AccordionTrigger>
                            <AccordionContent className="text-muted-foreground">
                                {item.answer}
                            </AccordionContent>
                        </AccordionItem>
                    ))}
                </Accordion>
            </div>
        </section>
    );
}

function FinalCta({ auth, canRegister }) {
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';

    return (
        <section className="border-t bg-primary text-primary-foreground">
            <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-12 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <div>
                    <Badge variant="secondary" className="mb-4 w-fit">
                        Listo para ordenar tu presencia digital
                    </Badge>
                    <h2 className="text-3xl font-semibold">Convierte visitas en clientes con una ruta clara.</h2>
                    <p className="mt-2 max-w-2xl text-sm opacity-80">
                        Publica tu negocio, manda clientes al micrositio y usa el panel para seguir la operacion.
                    </p>
                </div>
                {auth?.user ? (
                    <Button variant="secondary" size="lg" asChild>
                        <Link href={panelUrl}>Abrir panel</Link>
                    </Button>
                ) : canRegister !== false ? (
                    <Button variant="secondary" size="lg" asChild>
                        <Link href={appRoute('register', undefined, '/register')}>Registrar negocio</Link>
                    </Button>
                ) : null}
            </div>
        </section>
    );
}

export default function Welcome({
    auth = { user: null },
    categories = [],
    newTenants = [],
    featuredTenants = [],
    heroImage = null,
    stats = {},
    canRegister = true,
}) {
    const tenants = newTenants.length ? newTenants : featuredTenants;

    return (
        <PublicLayout>
            <Head title="Conecta HN - Plataforma para comercio local" />

            <HeroSection
                auth={auth}
                canRegister={canRegister}
                heroImage={heroImage}
                stats={stats}
            />
            <ValueSection />
            <CategorySection categories={categories} />
            <TenantsSection tenants={tenants} />
            <ProcessSection />
            <PricingSection canRegister={canRegister} />
            <FaqSection />
            <FinalCta auth={auth} canRegister={canRegister} />
        </PublicLayout>
    );
}
