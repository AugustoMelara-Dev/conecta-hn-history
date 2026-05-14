import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    Columns3,
    ExternalLink,
    Globe,
    Info,
    KanbanSquare,
    MapPin,
    MessageCircle,
    Phone,
    ShoppingBag,
    Star,
    Store,
    Trophy,
} from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
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
import { Separator } from '@/Components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import FloatingCart from '@/Components/Tenant/FloatingCart';
import ProductActionArea from '@/Components/Tenant/ProductActionArea';
import UpsellCard from '@/Components/Tenant/UpsellCard';
import useCartStore from '@/Stores/useCartStore';
import { getContrastColor } from '@/Utils/colorUtils';
import { safeJsonLd } from '@/lib/safeJsonLd';
import { cn } from '@/lib/utils';

const PLAN = {
    SEMILLA: 'semilla',
    PRO: 'pro',
    ENTERPRISE: 'enterprise',
};

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
function getInitials(name = 'CN') {
    return String(name)
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'CN';
}

function getWhatsappUrl(number, tenantName) {
    if (!number) {
        return null;
    }

    const phone = String(number).replace(/\D/g, '');

    if (!phone) {
        return null;
    }

    return `https://wa.me/${phone}?text=${encodeURIComponent(`Hola ${tenantName || 'equipo'}!`)}`;
}

function getCityName(tenant) {
    if (typeof tenant?.city === 'string') {
        return tenant.city;
    }

    return tenant?.city?.name || 'Honduras';
}

function isRestaurantCategory(categoryName) {
    return ['restaurantes', 'comida', 'restaurante', 'menu'].includes(
        String(categoryName || '').toLowerCase()
    );
}

function formatPrice(product) {
    if (product?.formatted_price) {
        return product.formatted_price;
    }

    const price = Number(product?.price || 0);

    return `L. ${price.toFixed(2)}`;
}

function formatMoney(value, currency = 'HNL') {
    const amount = Number(value || 0);
    const normalizedCurrency = String(currency || 'HNL').toUpperCase();

    try {
        return new Intl.NumberFormat('es-HN', {
            style: 'currency',
            currency: normalizedCurrency,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `L. ${amount.toFixed(2)}`;
    }
}

function LoadingState() {
    return (
        <main className="grid min-h-screen place-items-center bg-background px-4">
            <Card className="w-full max-w-sm text-center">
                <CardHeader>
                    <span className="mx-auto grid size-12 place-items-center rounded-md bg-muted text-muted-foreground">
                        <Store className="size-5" aria-hidden="true" />
                    </span>
                    <CardTitle>Cargando tienda</CardTitle>
                    <CardDescription>Estamos preparando el micrositio del negocio.</CardDescription>
                </CardHeader>
            </Card>
        </main>
    );
}

function StatusBadge({ isOpen }) {
    return (
        <Badge variant={isOpen ? 'default' : 'secondary'} className="gap-2">
            <span
                className={cn(
                    'size-2 rounded-full',
                    isOpen ? 'bg-primary-foreground' : 'bg-muted-foreground'
                )}
                aria-hidden="true"
            />
            {isOpen ? 'Abierto' : 'Cerrado'}
        </Badge>
    );
}

function TenantHero({ tenant, isOpen, totalProductsCount, productsCount, categoryName, cityName, whatsappUrl }) {
    return (
        <header className="border-b bg-muted/30" data-testid="microsite-hero">
            <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <nav className="flex items-center justify-between gap-3" aria-label="Navegacion del micrositio">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={safeRoute('directory.index', '/directorio')}>
                            Directorio
                        </Link>
                    </Button>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={safeRoute('home', '/')}>Vito</Link>
                    </Button>
                </nav>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-stretch">
                    <Card className="overflow-hidden">
                        <div className="relative aspect-[16/7] min-h-56 bg-muted">
                            {tenant.cover ? (
                                <img
                                    src={tenant.cover}
                                    alt={`Portada de ${tenant.name}`}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="grid h-full place-items-center bg-[var(--tenant-primary)] text-[var(--tenant-primary-foreground)]">
                                    <Store className="size-16 opacity-80" aria-hidden="true" />
                                </div>
                            )}
                        </div>
                        <CardContent className="flex flex-col gap-5 p-5 md:flex-row md:items-end md:p-6">
                            <Avatar className="size-24 rounded-md border bg-background md:size-28">
                                <AvatarImage src={tenant.logo || undefined} alt={`Logo de ${tenant.name}`} />
                                <AvatarFallback className="rounded-md text-xl font-semibold">
                                    {getInitials(tenant.name)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="flex min-w-0 flex-1 flex-col gap-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge isOpen={isOpen} />
                                    <Badge variant="outline">{categoryName}</Badge>
                                </div>

                                <div>
                                    <h1 className="text-3xl font-semibold tracking-normal text-foreground md:text-5xl">
                                        {tenant.name}
                                    </h1>
                                    <p className="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground md:text-base">
                                        {tenant.slogan || tenant.description || 'Negocio local disponible en Vito.'}
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                    <span className="inline-flex items-center gap-2">
                                        <MapPin className="size-4" aria-hidden="true" />
                                        {cityName}
                                    </span>
                                    <span className="inline-flex items-center gap-2">
                                        <ShoppingBag className="size-4" aria-hidden="true" />
                                        {productsCount} de {totalProductsCount || productsCount} productos visibles
                                    </span>
                                    {tenant.features?.can_show_reviews && (
                                        <span className="inline-flex items-center gap-2">
                                            <Star className="size-4" aria-hidden="true" />
                                            {tenant.rating || '4.8'} ({tenant.reviews_count || 0})
                                        </span>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Contacto rapido</CardTitle>
                            <CardDescription>Canales principales para comprar o consultar.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <Button variant="outline" asChild className="justify-start">
                                <a href="#catalogo">
                                    <ShoppingBag data-icon="inline-start" aria-hidden="true" />
                                    Ver {isRestaurantCategory(categoryName) ? 'menu' : 'catalogo'}
                                </a>
                            </Button>
                            {whatsappUrl ? (
                                <Button asChild className="justify-start">
                                    <a href={whatsappUrl} target="_blank" rel="noopener noreferrer">
                                        <MessageCircle data-icon="inline-start" aria-hidden="true" />
                                        Escribir por WhatsApp
                                    </a>
                                </Button>
                            ) : (
                                <Button disabled className="justify-start">
                                    <MessageCircle data-icon="inline-start" aria-hidden="true" />
                                    WhatsApp no disponible
                                </Button>
                            )}
                            {tenant.phone && (
                                <Button variant="outline" asChild className="justify-start">
                                    <a href={`tel:${tenant.phone}`} aria-label={`Llamar a ${tenant.name}`}>
                                        <Phone data-icon="inline-start" aria-hidden="true" />
                                        Llamar
                                    </a>
                                </Button>
                            )}
                            {tenant.website_url && (
                                <Button variant="outline" asChild className="justify-start">
                                    <a href={tenant.website_url} target="_blank" rel="noopener noreferrer">
                                        <Globe data-icon="inline-start" aria-hidden="true" />
                                        Sitio web
                                    </a>
                                </Button>
                            )}
                        </CardContent>
                        <CardFooter>
                            <p className="text-sm leading-6 text-muted-foreground">
                                {tenant.address || 'Direccion no especificada'}
                            </p>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </header>
    );
}

function ProductGridSection({
    products,
    tenant,
    tenantPlan,
    hasHiddenProducts = false,
    hiddenProductsCount = 0,
    isOwner = false,
}) {
    const isPro = tenantPlan !== PLAN.SEMILLA;

    if (!products?.length) {
        const showPlanLimit = hasHiddenProducts && isOwner;

        return (
            <div className="flex flex-col gap-4" data-testid="microsite-product-empty">
                <Alert>
                    <Info className="mr-2 inline size-4" aria-hidden="true" />
                    <AlertTitle>
                        {showPlanLimit ? 'Productos pendientes por publicar' : 'Catalogo vacio'}
                    </AlertTitle>
                    <AlertDescription>
                        {showPlanLimit
                            ? `Tienes ${hiddenProductsCount} productos fuera de la vista publica.`
                            : 'Este negocio aun no ha publicado productos visibles.'}
                    </AlertDescription>
                </Alert>

                {showPlanLimit && (
                    <UpsellCard
                        hiddenCount={hiddenProductsCount}
                        isOwner={isOwner}
                        tenantWhatsapp={tenant?.whatsapp || tenant?.whatsapp_number}
                        tenantName={tenant?.name}
                        tenantSlug={tenant?.slug}
                    />
                )}
            </div>
        );
    }

    const hasModifiers = (product) => product.has_modifiers || (product.modifier_groups?.length > 0);

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="microsite-product-grid">
            {products.map((product) => (
                <Card key={product.id} className="overflow-hidden" data-testid="microsite-product-card">
                    <div className="aspect-[4/3] bg-muted">
                        <img
                            src={product.image}
                            alt={product.name}
                            className="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <CardTitle className="line-clamp-2 text-lg">{product.name}</CardTitle>
                            <Badge variant="secondary" className="shrink-0">
                                {hasModifiers(product) && isPro ? 'Desde ' : ''}
                                {formatPrice(product)}
                            </Badge>
                        </div>
                        <CardDescription className="line-clamp-2 min-h-10">
                            {product.description || 'Producto disponible.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {hasModifiers(product) && isPro && (
                            <Badge variant="outline">
                                {product.modifier_groups?.length || 0} opciones
                            </Badge>
                        )}
                        {product.is_featured && <Badge variant="outline">Destacado</Badge>}
                    </CardContent>
                    <CardFooter>
                        <ProductActionArea
                            product={product}
                            tenantId={tenant.id}
                            tenantPlan={tenantPlan}
                            tenantWhatsapp={tenant.whatsapp || tenant.whatsapp_number}
                            tenantName={tenant.name}
                            tenantSlug={tenant.slug}
                            variant="default"
                            className="w-full"
                        />
                    </CardFooter>
                </Card>
            ))}

            {hasHiddenProducts && (
                <UpsellCard
                    hiddenCount={hiddenProductsCount}
                    isOwner={isOwner}
                    tenantWhatsapp={tenant?.whatsapp || tenant?.whatsapp_number}
                    tenantName={tenant?.name}
                    tenantSlug={tenant?.slug}
                />
            )}
        </div>
    );
}

function InfoSection({ tenant, isOpen, openingHours }) {
    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <Card>
                <CardHeader>
                    <CardTitle>Sobre el negocio</CardTitle>
                    <CardDescription>Informacion publica para decidir con confianza.</CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="whitespace-pre-line text-sm leading-6 text-muted-foreground">
                        {tenant.about || tenant.description || 'Bienvenido a nuestro negocio.'}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Detalles</CardTitle>
                    <CardDescription>Ubicacion, horario y enlaces utiles.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="flex items-start gap-3">
                        <MapPin className="mt-0.5 size-4 text-muted-foreground" aria-hidden="true" />
                        <div>
                            <p className="text-sm font-medium">Ubicacion</p>
                            <p className="text-sm text-muted-foreground">{tenant.address || 'No especificada'}</p>
                        </div>
                    </div>
                    <div className="flex items-start gap-3">
                        <Clock className="mt-0.5 size-4 text-muted-foreground" aria-hidden="true" />
                        <div>
                            <p className="text-sm font-medium">Estado</p>
                            <p className="text-sm text-muted-foreground">{isOpen ? 'Abierto ahora' : 'Cerrado'}</p>
                        </div>
                    </div>
                    {tenant.website_url && (
                        <div className="flex items-start gap-3">
                            <Globe className="mt-0.5 size-4 text-muted-foreground" aria-hidden="true" />
                            <div>
                                <p className="text-sm font-medium">Sitio web</p>
                                <a
                                    href={tenant.website_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                >
                                    Visitar <ExternalLink className="size-3" aria-hidden="true" />
                                </a>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card className="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Horario</CardTitle>
                    <CardDescription>Disponibilidad publicada por el negocio.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {openingHours?.length ? (
                        openingHours.map((hour) => (
                            <div key={hour.day_of_week} className="rounded-md border bg-muted/30 p-3">
                                <p className="text-sm font-medium">{hour.day_name}</p>
                                <p className="mt-1 text-sm text-muted-foreground">{hour.formatted}</p>
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground">Horario no publicado.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function ReviewsSection({ tenant }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Resenas</CardTitle>
                <CardDescription>Pronto habra comentarios verificados de clientes.</CardDescription>
            </CardHeader>
            <CardContent>
                <Alert>
                    <MessageCircle className="mr-2 inline size-4" aria-hidden="true" />
                    <AlertTitle>Resenas proximamente</AlertTitle>
                    <AlertDescription>
                        {tenant.name} todavia no tiene resenas publicadas en esta vista.
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
    );
}

function CrmSection({ crmMicrosite }) {
    if (!crmMicrosite?.enabled) {
        return (
            <Alert>
                <Info className="mr-2 inline size-4" aria-hidden="true" />
                <AlertTitle>CRM en despliegue gradual</AlertTitle>
                <AlertDescription>
                    El mini-CRM aun no esta habilitado para este negocio en el rollout actual.
                </AlertDescription>
            </Alert>
        );
    }

    const summary = crmMicrosite.summary || {};
    const pipelines = Array.isArray(crmMicrosite.pipelines) ? crmMicrosite.pipelines : [];
    const deals = Array.isArray(crmMicrosite.deals) ? crmMicrosite.deals : [];
    const tasks = Array.isArray(crmMicrosite.tasks) ? crmMicrosite.tasks : [];

    return (
        <div className="flex flex-col gap-4" data-testid="microsite-crm-tab">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription>Pipelines</CardDescription>
                        <CardTitle className="text-2xl">{summary.pipelines || 0}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription>Deals abiertos</CardDescription>
                        <CardTitle className="text-2xl">{summary.open_deals || 0}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription>Deals ganados</CardDescription>
                        <CardTitle className="text-2xl">{summary.won_deals || 0}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription>Tareas por vencer</CardDescription>
                        <CardTitle className="text-2xl">{summary.tasks_due || 0}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KanbanSquare className="size-4" aria-hidden="true" />
                            Pipelines
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {pipelines.length > 0 ? pipelines.map((pipeline) => (
                            <div key={pipeline.id} className="rounded-md border p-3">
                                <p className="text-sm font-medium">{pipeline.name}</p>
                                <p className="text-xs text-muted-foreground">
                                    {pipeline.stages_count || 0} etapas
                                </p>
                            </div>
                        )) : (
                            <p className="text-sm text-muted-foreground">Sin pipelines visibles.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Trophy className="size-4" aria-hidden="true" />
                            Deals recientes
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {deals.length > 0 ? deals.map((deal) => (
                            <div key={deal.id} className="rounded-md border p-3">
                                <p className="text-sm font-medium">{deal.title}</p>
                                <p className="text-xs text-muted-foreground">
                                    {deal.pipeline || 'Pipeline'} - {deal.stage || 'Etapa'}
                                </p>
                                <p className="mt-1 text-sm">{formatMoney(deal.value, deal.currency)}</p>
                            </div>
                        )) : (
                            <p className="text-sm text-muted-foreground">Sin deals visibles.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CheckCircle2 className="size-4" aria-hidden="true" />
                            Tareas activas
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {tasks.length > 0 ? tasks.map((task) => (
                            <div key={task.id} className="rounded-md border p-3">
                                <p className="text-sm font-medium">{task.title}</p>
                                <p className="text-xs text-muted-foreground">
                                    {task.task_type || 'seguimiento'} - {task.priority || 'normal'}
                                </p>
                            </div>
                        )) : (
                            <p className="text-sm text-muted-foreground">Sin tareas activas.</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function MobileActionBar({ tenant, whatsappUrl }) {
    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur md:hidden"
            aria-label="Acciones rapidas"
            data-testid="microsite-mobile-action-bar"
        >
            <div className="grid grid-cols-3 gap-1 p-2">
                <Button variant="ghost" asChild>
                    <a href="#catalogo" aria-label="Ir a catalogo">
                        <ShoppingBag data-icon="inline-start" aria-hidden="true" />
                        Catalogo
                    </a>
                </Button>
                {tenant.phone ? (
                    <Button variant="ghost" asChild>
                        <a href={`tel:${tenant.phone}`}>
                            <Phone data-icon="inline-start" aria-hidden="true" />
                            Llamar
                        </a>
                    </Button>
                ) : (
                    <Button variant="ghost" disabled>
                        <Phone data-icon="inline-start" aria-hidden="true" />
                        Llamar
                    </Button>
                )}
                {whatsappUrl ? (
                    <Button asChild>
                        <a href={whatsappUrl} target="_blank" rel="noopener noreferrer">
                            <MessageCircle data-icon="inline-start" aria-hidden="true" />
                            WhatsApp
                        </a>
                    </Button>
                ) : (
                    <Button disabled>
                        <MessageCircle data-icon="inline-start" aria-hidden="true" />
                        WhatsApp
                    </Button>
                )}
            </div>
        </nav>
    );
}

export default function TenantShow({
    tenant,
    products,
    storefrontCapabilities = {},
    hasHiddenProducts = false,
    totalProductsCount = 0,
    hiddenProductsCount = 0,
    isOwner = false,
    seoStructuredData,
    isOpen = true,
    openingHours = [],
    crmMicrosite = null,
}) {
    const initForTenant = useCartStore((state) => state.initForTenant);
    const effectiveTenantPlan = storefrontCapabilities.checkout ? PLAN.PRO : PLAN.SEMILLA;

    useEffect(() => {
        if (effectiveTenantPlan !== PLAN.SEMILLA && tenant?.slug) {
            initForTenant(
                tenant.slug,
                tenant.whatsapp || tenant.whatsapp_number,
                tenant.name,
                tenant.id
            );
        }
    }, [effectiveTenantPlan, initForTenant, tenant?.id, tenant?.name, tenant?.slug, tenant?.whatsapp, tenant?.whatsapp_number]);

    if (!tenant) {
        return <LoadingState />;
    }

    const safeProducts = Array.isArray(products)
        ? products
        : Array.isArray(tenant.products)
            ? tenant.products
            : [];
    const primaryColor = tenant.brand_color || tenant.primary_color || '#2563EB';
    const primaryTextColor = getContrastColor(primaryColor);
    const categoryName = tenant.category?.name || 'General';
    const cityName = getCityName(tenant);
    const whatsappNumber = tenant.whatsapp || tenant.whatsapp_number || '';
    const whatsappUrl = getWhatsappUrl(whatsappNumber, tenant.name);
    const catalogLabel = isRestaurantCategory(categoryName) ? 'Menu' : 'Catalogo';
    const pageTitle = `${tenant.name || 'Tienda'} | ${categoryName} en ${cityName} | Vito`;
    const pageDescription = String(
        tenant.description ||
        tenant.slogan ||
        `Descubre ${tenant.name} en Vito. Negocios locales de Honduras.`
    ).substring(0, 160);
    const pageImage = tenant.hero_image || tenant.cover || tenant.banner_path || 'https://conecta-hn.com/og-default.jpg';
    const showCrmTab = Boolean(isOwner && crmMicrosite?.enabled);

    return (
        <div
            style={{
                '--tenant-primary': primaryColor,
                '--tenant-primary-foreground': primaryTextColor,
            }}
            className="min-h-screen bg-background pb-20 text-foreground md:pb-0"
            data-testid="microsite-page"
        >
            <Head title={pageTitle}>
                <meta name="description" content={pageDescription} />
                <meta property="og:title" content={pageTitle} />
                <meta property="og:description" content={pageDescription} />
                <meta property="og:type" content="business.business" />
                <meta property="og:image" content={pageImage} />
                {seoStructuredData && (
                    <script type="application/ld+json">
                        {safeJsonLd(seoStructuredData)}
                    </script>
                )}
            </Head>

            <TenantHero
                tenant={tenant}
                isOpen={isOpen}
                totalProductsCount={totalProductsCount}
                productsCount={safeProducts.length}
                categoryName={categoryName}
                cityName={cityName}
                whatsappUrl={whatsappUrl}
            />

            <main id="catalogo" className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                <Alert>
                    <CalendarClock className="mr-2 inline size-4" aria-hidden="true" />
                    <AlertTitle>{isOpen ? 'Abierto para consultas' : 'Actualmente cerrado'}</AlertTitle>
                    <AlertDescription>
                        Los productos y datos de contacto se mantienen publicados para que puedas consultar disponibilidad.
                    </AlertDescription>
                </Alert>

                <Tabs defaultValue="products" className="w-full" data-testid="microsite-tabs">
                    <TabsList className={cn('grid w-full lg:w-[36rem]', showCrmTab ? 'grid-cols-4' : 'grid-cols-3')}>
                        <TabsTrigger value="products">{catalogLabel}</TabsTrigger>
                        <TabsTrigger value="info">Informacion</TabsTrigger>
                        <TabsTrigger value="reviews">Resenas</TabsTrigger>
                        {showCrmTab && (
                            <TabsTrigger value="crm">
                                <Columns3 aria-hidden="true" />
                                CRM
                            </TabsTrigger>
                        )}
                    </TabsList>

                    <TabsContent value="products" className="mt-6">
                        <ProductGridSection
                            products={safeProducts}
                            tenant={tenant}
                            tenantPlan={effectiveTenantPlan}
                            hasHiddenProducts={hasHiddenProducts}
                            hiddenProductsCount={hiddenProductsCount}
                            isOwner={isOwner}
                        />
                    </TabsContent>

                    <TabsContent value="info" className="mt-6">
                        <InfoSection tenant={tenant} isOpen={isOpen} openingHours={openingHours} />
                    </TabsContent>

                    <TabsContent value="reviews" className="mt-6">
                        <ReviewsSection tenant={tenant} />
                    </TabsContent>

                    {showCrmTab && (
                        <TabsContent value="crm" className="mt-6">
                            <CrmSection crmMicrosite={crmMicrosite} />
                        </TabsContent>
                    )}
                </Tabs>
            </main>

            <Separator />

            <footer className="mx-auto hidden max-w-7xl items-center justify-between gap-4 px-4 py-6 text-sm text-muted-foreground sm:px-6 md:flex lg:px-8">
                <span>Powered by Vito Business OS</span>
                <Button variant="link" asChild className="px-0">
                    <Link href={safeRoute('home', '/')}>Crear mi micrositio</Link>
                </Button>
            </footer>

            {effectiveTenantPlan !== PLAN.SEMILLA && <FloatingCart />}
            <MobileActionBar tenant={tenant} whatsappUrl={whatsappUrl} />
        </div>
    );
}
