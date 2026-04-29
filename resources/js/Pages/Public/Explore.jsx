import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    Clock3,
    MapPin,
    Search,
    SlidersHorizontal,
    Sparkles,
    Star,
} from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { PUBLIC_DISPLAY_FONT } from '@/lib/publicTypography';
import {
    getTenantInitials,
    resolveTenantCategoryName,
    resolveTenantCityName,
    resolveTenantDescription,
    resolveTenantPlanLabel,
    resolveTenantVisual,
} from '@/lib/tenantPresentation';

function resolveCityVisual(city) {
    if (!city?.hero_image) {
        return null;
    }

    if (
        city.hero_image.startsWith('http://')
        || city.hero_image.startsWith('https://')
        || city.hero_image.startsWith('/storage/')
    ) {
        return city.hero_image;
    }

    return `/storage/${city.hero_image}`;
}

function TenantImage({ tenant, className = '', overlay = false }) {
    const visual = resolveTenantVisual(tenant);

    if (visual) {
        return (
            <div className={`relative ${className}`}>
                <img src={visual} alt={tenant.name} className="h-full w-full object-cover" />
                {overlay ? <div className="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/15 to-transparent" /> : null}
            </div>
        );
    }

    return (
        <div className={`flex items-center justify-center bg-[radial-gradient(circle_at_top_left,#d4d4d4,transparent_28%),radial-gradient(circle_at_bottom_right,#111827,transparent_36%),#0f172a] text-4xl font-semibold text-white ${className}`}>
            {getTenantInitials(tenant)}
        </div>
    );
}

function TopSearch({ searchQuery, setSearchQuery, cities = [] }) {
    const [cityFilter, setCityFilter] = useState('all');

    const handleSubmit = (event) => {
        event.preventDefault();

        router.get(
            route('directory.index'),
            {
                ...(searchQuery.trim() ? { search: searchQuery } : {}),
                ...(cityFilter && cityFilter !== 'all' ? { city: cityFilter } : {}),
            },
        );
    };

    return (
        <section className="border-b border-stone-200 bg-white">
            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div className="max-w-2xl">
                        <p className="text-sm font-semibold uppercase tracking-[0.28em] text-slate-500">Negocios</p>
                        <h1
                            className="mt-3 text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            Descubre negocios locales desde una sola experiencia publica.
                        </h1>
                        <p className="mt-4 text-lg leading-8 text-slate-600">
                            Esta vista toma la idea editorial de Stitch y la aterriza con datos reales: destacados, categorias, ciudades y salida clara al buscador completo.
                        </p>
                    </div>

                    <Button asChild className="h-auto rounded-full bg-slate-950 px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-slate-800">
                        <Link href={route('tenant.register')}>
                            Registrar negocio
                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="mt-8 rounded-[32px] border border-stone-200 bg-[#f3f3f1] p-3 shadow-sm" data-testid="explore-search-form">
                    <div className="grid gap-3 lg:grid-cols-[1.2fr,0.42fr,auto,auto]">
                        <div className="relative">
                            <label htmlFor="explore-search" className="sr-only">
                                Buscar negocios, servicios o ubicaciones
                            </label>
                            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="explore-search"
                                type="text"
                                value={searchQuery}
                                onChange={(event) => setSearchQuery(event.target.value)}
                                placeholder="Buscar negocios, servicios o ubicaciones..."
                                className="h-14 rounded-2xl border-stone-200 bg-white px-11 text-base text-slate-950 placeholder:text-slate-400 focus-visible:ring-slate-950"
                                data-testid="explore-search-input"
                            />
                        </div>

                        <div className="relative">
                            <label htmlFor="explore-city" className="sr-only">
                                Ciudad
                            </label>
                            <MapPin className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Select value={cityFilter} onValueChange={setCityFilter}>
                                <SelectTrigger
                                    id="explore-city"
                                    className="h-14 rounded-2xl border-stone-200 bg-white pl-11 text-sm text-slate-900 focus:ring-slate-950"
                                    data-testid="explore-city-select"
                                >
                                    <SelectValue placeholder="Todo Honduras" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">Todo Honduras</SelectItem>
                                        {cities.map((city) => (
                                            <SelectItem key={city.id} value={city.slug}>{city.name}</SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            type="submit"
                            className="h-14 rounded-2xl bg-slate-950 px-6 text-sm font-semibold text-white transition-colors hover:bg-slate-800"
                            data-testid="explore-search-submit"
                        >
                            Buscar
                        </Button>

                        <Button
                            asChild
                            variant="outline"
                            className="h-14 rounded-2xl border-stone-200 bg-white px-5 text-sm font-semibold text-slate-700 transition-colors hover:border-stone-300 hover:text-slate-950"
                        >
                            <Link href={route('directory.index')}>
                                <SlidersHorizontal data-icon="inline-start" aria-hidden="true" />
                                Filtros
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </section>
    );
}

function CategoryRail({ categories = [] }) {
    if (!categories.length) {
        return null;
    }

    return (
        <section className="bg-[#f3f3f1] py-8">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mb-4 flex items-center justify-between gap-4">
                    <h2 className="text-sm font-semibold uppercase tracking-[0.26em] text-slate-500">Categorias</h2>
                    <Link href={route('directory.index')} className="text-sm font-semibold text-slate-700 transition hover:text-slate-950">
                        Ver todas
                    </Link>
                </div>

                <div className="flex gap-3 overflow-x-auto pb-2">
                    {categories.map((category, index) => (
                        <Link
                            key={category.slug}
                            href={route('directory.index', { category: category.slug })}
                            className={`whitespace-nowrap rounded-full border px-5 py-3 text-sm font-semibold transition ${
                                index === 0
                                    ? 'border-slate-950 bg-slate-950 text-white'
                                    : 'border-stone-200 bg-white text-slate-700 hover:border-stone-300 hover:text-slate-950'
                            }`}
                        >
                            {category.name}
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}

function FeaturedSplit({ featuredTenants = [] }) {
    const heroTenant = featuredTenants[0];
    const supportingTenants = featuredTenants.slice(1, 3);

    if (!heroTenant) {
        return null;
    }

    return (
        <section className="bg-[#f3f3f1] pb-16">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <h2
                            className="text-3xl font-semibold tracking-tight text-slate-950"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            Destacados cerca de ti
                        </h2>
                        <p className="mt-2 text-sm text-slate-600">
                            Una capa mas editorial para que descubrir se sienta mas claro que abrir un directorio plano.
                        </p>
                    </div>
                    <Link href={route('directory.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 transition hover:text-slate-950">
                        Ir al buscador completo
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>

                <div className="grid gap-6 md:grid-cols-12 md:auto-rows-fr">
                    <Link
                        href={route('tenant.microsite.show', { tenant: heroTenant.slug })}
                        className="group relative overflow-hidden rounded-[30px] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-2xl md:col-span-7"
                    >
                        <TenantImage tenant={heroTenant} className="aspect-[4/4.5] md:aspect-auto md:h-full" overlay />
                        <div className="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                            <div className="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-slate-950">
                                <Sparkles className="h-3.5 w-3.5" />
                                Seleccion editorial
                            </div>
                            <h3
                                className="mt-4 text-3xl font-semibold text-white"
                                style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                            >
                                {heroTenant.name}
                            </h3>
                            <p className="mt-3 max-w-xl text-sm leading-7 text-white/78">
                                {resolveTenantDescription(
                                    heroTenant,
                                    'Negocio local con una presencia publica mas fuerte, mejor resuelta y lista para convertir visitas en accion.',
                                )}
                            </p>
                            <div className="mt-5 flex flex-wrap items-center gap-4 text-white">
                                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold">
                                    <MapPin className="h-4 w-4 text-white" />
                                    {resolveTenantCityName(heroTenant)}
                                </span>
                                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold">
                                    <Star className="h-4 w-4 fill-current text-amber-300" />
                                    {resolveTenantPlanLabel(heroTenant)}
                                </span>
                            </div>
                        </div>
                    </Link>

                    <div className="grid gap-6 md:col-span-5">
                        {supportingTenants.map((tenant, index) => (
                            <Link
                                key={tenant.id}
                                href={route('tenant.microsite.show', { tenant: tenant.slug })}
                                className="group relative overflow-hidden rounded-[30px] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                            >
                                <TenantImage tenant={tenant} className="aspect-[4/2.55]" overlay />
                                <div className="absolute inset-x-0 bottom-0 p-5">
                                    <div className="mb-2 inline-flex items-center gap-2 rounded-full bg-white/85 px-3 py-1 text-[11px] font-semibold text-slate-700">
                                        {index === 0 ? <MapPin className="h-3.5 w-3.5 text-slate-950" /> : <BadgeCheck className="h-3.5 w-3.5 text-slate-950" />}
                                        {index === 0 ? resolveTenantCityName(tenant) : 'Perfil destacado'}
                                    </div>
                                    <h3 className="text-xl font-semibold text-white" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                        {tenant.name}
                                    </h3>
                                    <p className="mt-1 text-sm text-white/75">{resolveTenantCategoryName(tenant)}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}

function BusinessGrid({ featuredTenants = [] }) {
    const gridTenants = useMemo(() => featuredTenants.slice(3, 11), [featuredTenants]);

    if (!gridTenants.length) {
        return null;
    }

    return (
        <section className="bg-white py-16">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mb-8 flex items-center justify-between gap-4">
                    <div>
                        <h2
                            className="text-2xl font-semibold tracking-tight text-slate-950"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            Recien integrados
                        </h2>
                        <p className="mt-2 text-sm text-slate-600">
                            Negocios nuevos con una presencia publica lista para explorar.
                        </p>
                    </div>
                    <Link href={route('directory.index')} className="inline-flex items-center gap-2 rounded-full border border-stone-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-stone-300 hover:text-slate-950">
                        Ver todos
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>

                <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                    {gridTenants.map((tenant) => (
                        <Link
                            key={tenant.id}
                            href={route('tenant.microsite.show', { tenant: tenant.slug })}
                            className="group flex h-full flex-col overflow-hidden rounded-[28px] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                        >
                            <div className="relative">
                                <TenantImage tenant={tenant} className="aspect-[4/3]" />
                                <div className="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/90 px-3 py-1 text-[11px] font-semibold text-slate-700">
                                    <Sparkles className="h-3.5 w-3.5 text-slate-950" />
                                    {resolveTenantPlanLabel(tenant)}
                                </div>
                            </div>

                            <div className="flex flex-1 flex-col p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="text-lg font-semibold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                            {tenant.name}
                                        </h3>
                                        <p className="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                            {resolveTenantCategoryName(tenant)}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-[#f3f3f1] px-2.5 py-1 text-xs font-bold text-slate-700">
                                        Nuevo
                                    </span>
                                </div>

                                <p className="mt-4 text-sm leading-7 text-slate-600">
                                    {resolveTenantDescription(
                                        tenant,
                                        'Perfil activo dentro de Vito Business OS con salida a contacto, catalogo o reserva segun el tipo de negocio.',
                                    )}
                                </p>

                                <div className="mt-auto flex items-center justify-between gap-3 border-t border-stone-100 pt-4">
                                    <span className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                        <Clock3 className="h-4 w-4 text-slate-950" />
                                        {resolveTenantCityName(tenant)}
                                    </span>
                                    <span className="text-sm font-semibold text-slate-950">Ver perfil</span>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}

function GrowthBanner({ stats = {} }) {
    return (
        <section className="bg-white pb-16">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="relative overflow-hidden rounded-[34px] bg-slate-950 px-8 py-10 text-white shadow-2xl shadow-slate-950/15 sm:px-10 sm:py-12">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.14),transparent_24%),radial-gradient(circle_at_bottom_right,rgba(148,163,184,0.12),transparent_30%)]" />
                    <div className="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
                        <div className="max-w-2xl">
                            <p className="text-sm font-semibold uppercase tracking-[0.28em] text-slate-300">Para negocios</p>
                            <h2
                                className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                                style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                            >
                                Tu negocio deberia estar aqui tambien?
                            </h2>
                            <p className="mt-4 text-lg leading-8 text-slate-300">
                                {stats?.total_tenants || 0}+ negocios ya entran con mejor presencia publica mientras la operacion interna se mantiene dentro del panel de Filament.
                            </p>
                            <div className="mt-6 flex flex-col gap-4 sm:flex-row">
                                <Link
                                    href={route('tenant.register')}
                                    className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-7 py-4 text-base font-semibold text-slate-950 transition hover:bg-slate-100"
                                >
                                    Registrar mi negocio
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                                <Link
                                    href={route('directory.index')}
                                    className="inline-flex items-center justify-center gap-2 rounded-full border border-white/15 px-7 py-4 text-base font-semibold text-white transition hover:border-white/30 hover:bg-white/5"
                                >
                                    Abrir buscador completo
                                    <Search className="h-4 w-4" />
                                </Link>
                            </div>
                        </div>

                        <div className="grid min-w-[280px] gap-4 sm:grid-cols-2">
                            <div className="rounded-[26px] border border-white/10 bg-white/5 p-5">
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Capa publica</p>
                                <p className="mt-3 text-3xl font-semibold text-white" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                    Unificada
                                </p>
                                <p className="mt-2 text-sm text-slate-400">Descubrir, buscar y aterrizar al negocio sin duplicar producto.</p>
                            </div>
                            <div className="rounded-[26px] border border-white/10 bg-white/5 p-5">
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Operacion</p>
                                <p className="mt-3 text-3xl font-semibold text-white" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                    Filament
                                </p>
                                <p className="mt-2 text-sm text-slate-400">Panel del negocio y admin sin inventar dashboards paralelos.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

function CitySection({ cities = [] }) {
    if (!cities.length) {
        return null;
    }

    return (
        <section className="border-t border-stone-200 bg-[#f3f3f1] py-16">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <h2
                        className="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl"
                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                    >
                        Explora por ciudad
                    </h2>
                    <p className="mt-2 text-sm text-slate-600">
                        La misma experiencia publica baja de categoria a ciudad sin romper la navegacion.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cities.map((city) => {
                        const visual = resolveCityVisual(city);

                        return (
                            <Link
                                key={city.slug}
                                href={route('city.show', { city: city.slug })}
                                className="group relative overflow-hidden rounded-[26px] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                            >
                                <div className="relative aspect-[4/2.8] overflow-hidden bg-slate-950">
                                    {visual ? (
                                        <img src={visual} alt={city.name} className="h-full w-full object-cover transition duration-700 group-hover:scale-105" />
                                    ) : (
                                        <div className="h-full w-full bg-[radial-gradient(circle_at_top_left,#d4d4d4,transparent_30%),radial-gradient(circle_at_bottom_right,#111827,transparent_34%),#0f172a]" />
                                    )}
                                    <div className="absolute inset-0 bg-black/35" />
                                    <div className="absolute inset-x-0 bottom-0 p-5">
                                        <div className="inline-flex items-center gap-2 text-sm text-slate-200">
                                            <MapPin className="h-4 w-4" />
                                            {city.department || 'Honduras'}
                                        </div>
                                        <h3 className="mt-2 text-xl font-semibold text-white" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                            {city.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-white/75">
                                            {city.tenants_count || 0} negocios activos
                                        </p>
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

export default function Explore({ featuredTenants = [], categories = [], cities = [], stats = {} }) {
    const [searchQuery, setSearchQuery] = useState('');

    return (
        <PublicLayout>
            <Head>
                <title>Negocios destacados en Honduras</title>
                <meta
                    name="description"
                    content="Descubre negocios locales en Honduras desde una sola experiencia publica: destacados, categorias, ciudades y un buscador completo para filtrar y comparar."
                />
                <link rel="canonical" href={route('public.explore')} />
            </Head>

            <TopSearch searchQuery={searchQuery} setSearchQuery={setSearchQuery} cities={cities} />
            <CategoryRail categories={categories} />
            <FeaturedSplit featuredTenants={featuredTenants} />
            <BusinessGrid featuredTenants={featuredTenants} />
            <GrowthBanner stats={stats} />
            <CitySection cities={cities} />
        </PublicLayout>
    );
}
