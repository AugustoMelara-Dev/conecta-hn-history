import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import FollowButton from '@/Components/Public/FollowButton';
import ReviewSection from '@/Components/Public/ReviewSection';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { BadgeCheck, Clock, ExternalLink, Globe, Lock, MapPin, MessageCircle, Phone, ShoppingBag, Star, Store } from 'lucide-react';
import { cn } from '@/lib/utils';
import FloatingCart from '@/Components/Tenant/FloatingCart';
import ProductActionArea from '@/Components/Tenant/ProductActionArea';
import useCartStore from '@/Stores/useCartStore';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';

const PLAN = { SEMILLA: 'semilla', PRO: 'pro', ENTERPRISE: 'enterprise' };

export default function TenantShow({
    tenant, products, filters = {}, tenantPlan = PLAN.SEMILLA, hasHiddenProducts = false, totalProductsCount = 0,
    hiddenProductsCount = 0, isOwner = false, isFollowing, followersCount, seoStructuredData, isOpen = true,
    openingHours = [], canonicalUrl,
}) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const initForTenant = useCartStore((state) => state.initForTenant);

    useEffect(() => setSearchQuery(filters.search || ''), [filters.search]);
    useEffect(() => {
        if (tenantPlan !== PLAN.SEMILLA && tenant?.slug) {
            initForTenant(tenant.slug, tenant.whatsapp || tenant.whatsapp_number, tenant.name, tenant.id);
        }
    }, [initForTenant, tenant, tenantPlan]);

    if (!tenant) {
        return <div className="flex min-h-screen items-center justify-center bg-white text-black"><Store className="h-8 w-8" /></div>;
    }

    const safeProducts = Array.isArray(products?.data) ? products.data : Array.isArray(products) ? products : Array.isArray(tenant.products) ? tenant.products : [];
    const pagination = Array.isArray(products?.links) ? products.links : [];
    const visibleTotal = Number(products?.total ?? safeProducts.length);
    const isPro = tenantPlan !== PLAN.SEMILLA;
    const whatsapp = tenant.whatsapp || tenant.whatsapp_number || '';
    const cityName = typeof tenant.city === 'string' ? tenant.city : (tenant.city?.name || 'Honduras');
    const pageTitle = `${tenant.name || 'Tienda'} | ${tenant.category?.name || 'Negocios'} en ${cityName} | Conecta HN`;
    const pageDescription = String(tenant.description || tenant.slogan || `Descubre ${tenant.name} en Conecta HN.`).substring(0, 160);
    const pageImage = tenant.hero_image || tenant.cover || tenant.banner_path || 'https://conecta-hn.com/og-default.jpg';

    const handleSearch = useCallback((event) => {
        event.preventDefault();
        router.get(route('tenant.microsite.show', tenant.slug), { search: searchQuery || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }, [searchQuery, tenant.slug]);

    const shell = (
        <>
            <nav className="fixed inset-x-0 top-0 z-50 border-b border-gray-200 bg-white/95 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <Link href={route('directory.index')} className="border border-black px-4 py-2 text-[10px] font-bold uppercase tracking-[0.24em] hover:bg-black hover:text-white">Directorio</Link>
                    <Link href="/" className="flex h-9 w-9 items-center justify-center border border-black bg-black text-xs font-black uppercase tracking-[0.24em] text-white">C</Link>
                </div>
            </nav>

            <header className="border-b border-gray-200 bg-white pt-16">
                <div className={cn('relative overflow-hidden border-b border-gray-200 bg-gray-50', isPro ? 'min-h-[22rem]' : 'min-h-[14rem]')}>
                    {tenant.cover ? <img src={tenant.cover} alt={tenant.name} className="absolute inset-0 h-full w-full object-cover grayscale" onError={(e) => { e.target.onerror = null; e.target.src = 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&h=600&fit=crop'; }} /> : <div className="absolute inset-0 flex items-center justify-center text-4xl font-black uppercase tracking-[0.4em] text-gray-100 md:text-7xl">{tenant.name}</div>}
                    <div className="absolute inset-0 bg-white/35" />
                </div>

                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[160px_minmax(0,1fr)] lg:px-8">
                    <div className="mx-auto flex h-32 w-32 items-center justify-center overflow-hidden border-4 border-black bg-white lg:mx-0">
                        {tenant.logo ? <img src={tenant.logo} alt={`${tenant.name} logo`} className="h-full w-full object-cover grayscale" /> : <span className="text-3xl font-black uppercase tracking-[0.24em]">{tenant.name?.substring(0, 2).toUpperCase()}</span>}
                    </div>
                    <div className="space-y-5 text-center lg:text-left">
                        <div className="flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                            <span className="border border-black px-3 py-1 text-[10px] font-bold uppercase tracking-[0.24em]">{tenant.category?.name || 'General'}</span>
                            <span className="border border-gray-300 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">{tenantPlan}</span>
                            {isPro ? <span className="inline-flex items-center gap-2 border border-black bg-black px-3 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-white"><BadgeCheck className="h-3.5 w-3.5" />Verificado</span> : null}
                        </div>
                        <div>
                            <h1 className="text-3xl font-black uppercase tracking-[0.18em] md:text-5xl">{tenant.name}</h1>
                            <div className="mt-3 flex flex-wrap items-center justify-center gap-4 text-xs font-bold uppercase tracking-[0.18em] text-gray-500 lg:justify-start">
                                <span className="inline-flex items-center gap-2"><MapPin className="h-4 w-4 text-black" />{cityName}</span>
                                <span className="inline-flex items-center gap-2"><span className={cn('h-2 w-2 border border-black', isOpen ? 'bg-black' : 'bg-white')} />{isOpen ? 'Abierto' : 'Cerrado'}</span>
                                {tenant.reviews_count ? <span className="inline-flex items-center gap-2"><Star className="h-4 w-4 fill-black text-black" />{tenant.rating || '4.8'} ({tenant.reviews_count})</span> : null}
                            </div>
                        </div>
                        <div className="grid gap-4 border-t border-gray-200 pt-5 md:grid-cols-3">
                            <Metric label="Catalogo visible" value={visibleTotal} />
                            <Metric label="Productos totales" value={totalProductsCount} />
                            <Metric label="Ocultos por plan" value={hiddenProductsCount} />
                        </div>
                        <div className="flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                            {!isOwner ? <FollowButton tenantId={tenant.id} isFollowing={isFollowing} followersCount={followersCount} className="rounded-none border-black bg-black text-white hover:bg-gray-800" /> : null}
                            {whatsapp ? <a href={`https://wa.me/${whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent(`Hola ${tenant.name}!`)}`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 border border-black bg-black px-5 py-3 text-[10px] font-bold uppercase tracking-[0.24em] text-white hover:bg-gray-800"><MessageCircle className="h-4 w-4" />WhatsApp</a> : null}
                            {tenant.phone ? <a href={`tel:${tenant.phone}`} className="inline-flex items-center gap-2 border border-black px-5 py-3 text-[10px] font-bold uppercase tracking-[0.24em] hover:bg-black hover:text-white"><Phone className="h-4 w-4" />Llamar</a> : null}
                        </div>
                    </div>
                </div>
            </header>
        </>
    );

    return (
        <div style={{ '--primary': '#000000', '--primary-color': '#000000', '--primary-text-color': '#ffffff' }} className="min-h-screen bg-white text-black selection:bg-black selection:text-white">
            <Head title={pageTitle}>
                <meta name="description" content={pageDescription} />
                <meta property="og:title" content={pageTitle} />
                <meta property="og:description" content={pageDescription} />
                <meta property="og:type" content="business.business" />
                <meta property="og:image" content={pageImage} />
                {canonicalUrl ? <link rel="canonical" href={canonicalUrl} /> : null}
                {seoStructuredData ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(seoStructuredData) }} /> : null}
            </Head>

            {shell}
            {isPro ? (
                <Tabs defaultValue="products" className="w-full">
                    <div className="sticky top-16 z-40 border-b border-gray-200 bg-white">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <TabsList className="h-14 bg-transparent p-0">
                                <TabsTrigger value="products" className="h-full rounded-none border-b-2 border-transparent px-0 pr-8 text-[11px] font-bold uppercase tracking-[0.24em] text-gray-500 data-[state=active]:border-black data-[state=active]:text-black">Catalogo</TabsTrigger>
                                <TabsTrigger value="info" className="h-full rounded-none border-b-2 border-transparent px-0 pr-8 text-[11px] font-bold uppercase tracking-[0.24em] text-gray-500 data-[state=active]:border-black data-[state=active]:text-black">Informacion</TabsTrigger>
                                <TabsTrigger value="reviews" className="h-full rounded-none border-b-2 border-transparent px-0 text-[11px] font-bold uppercase tracking-[0.24em] text-gray-500 data-[state=active]:border-black data-[state=active]:text-black">Resenas</TabsTrigger>
                            </TabsList>
                        </div>
                    </div>
                    <main id="catalogo" className="mx-auto max-w-7xl px-4 py-10 pb-32 sm:px-6 lg:px-8">
                        <TabsContent value="products" className="mt-0 space-y-8"><Toolbar searchQuery={searchQuery} setSearchQuery={setSearchQuery} handleSearch={handleSearch} visibleTotal={visibleTotal} currentCount={safeProducts.length} hiddenProductsCount={hiddenProductsCount} hasHiddenProducts={hasHiddenProducts} /><ProductGridSection products={safeProducts} pagination={pagination} tenant={tenant} tenantPlan={tenantPlan} columns={3} hiddenProductsCount={hiddenProductsCount} hasHiddenProducts={hasHiddenProducts} isOwner={isOwner} /></TabsContent>
                        <TabsContent value="info" className="mt-8"><InfoSection tenant={tenant} isOpen={isOpen} openingHours={openingHours} /></TabsContent>
                        <TabsContent value="reviews" className="mt-8"><ReviewSection tenant={tenant} reviews={tenant.reviews || []} /></TabsContent>
                    </main>
                </Tabs>
            ) : (
                <main className="mx-auto max-w-4xl px-4 py-10 pb-28"><Toolbar searchQuery={searchQuery} setSearchQuery={setSearchQuery} handleSearch={handleSearch} visibleTotal={visibleTotal} currentCount={safeProducts.length} hiddenProductsCount={hiddenProductsCount} hasHiddenProducts={hasHiddenProducts} /><ProductGridSection products={safeProducts} pagination={pagination} tenant={tenant} tenantPlan={tenantPlan} columns={2} hiddenProductsCount={hiddenProductsCount} hasHiddenProducts={hasHiddenProducts} isOwner={isOwner} /><div className="mt-12 border border-gray-200 bg-white p-6"><h3 className="mb-4 text-sm font-black uppercase tracking-[0.24em]">Informacion del negocio</h3><p className="mb-6 text-sm leading-7 text-gray-600">{tenant.description || 'Bienvenido a nuestro negocio.'}</p><div className="space-y-3 text-xs font-bold uppercase tracking-[0.18em] text-gray-500"><div className="flex items-center gap-3"><MapPin className="h-4 w-4 text-black" />{tenant.address || 'Honduras'}</div><div className="flex items-center gap-3"><Clock className="h-4 w-4 text-black" />{isOpen ? 'Abierto ahora' : 'Cerrado'}</div></div></div></main>
            )}

            {isPro ? <FloatingCart /> : null}
            <nav className="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white md:hidden"><div className="grid h-16 grid-cols-4"><Link href={`/${tenant.slug}`} className="flex flex-col items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 hover:text-black"><Store className="h-5 w-5" /><span>Inicio</span></Link><a href="#catalogo" className="flex flex-col items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 hover:text-black"><ShoppingBag className="h-5 w-5" /><span>Catalogo</span></a>{tenant.phone ? <a href={`tel:${tenant.phone}`} className="flex flex-col items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 hover:text-black"><Phone className="h-5 w-5" /><span>Llamar</span></a> : <div />}{whatsapp ? <a href={`https://wa.me/${whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent(`Hola ${tenant.name}!`)}`} target="_blank" rel="noopener noreferrer" className="flex flex-col items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.18em] text-black"><MessageCircle className="h-5 w-5" /><span>WhatsApp</span></a> : <div />}</div></nav>
            <footer className="hidden border-t border-gray-200 bg-white py-8 md:block"><div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8"><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Powered by <Link href="/" className="text-black hover:text-gray-600">Conecta HN</Link></p></div></footer>
        </div>
    );
}

function Metric({ label, value }) { return <div className="border border-gray-200 p-4 text-center lg:text-left"><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">{label}</p><p className="mt-2 text-2xl font-black uppercase tracking-[0.12em]">{value}</p></div>; }

function Toolbar({ searchQuery, setSearchQuery, handleSearch, visibleTotal, currentCount, hiddenProductsCount, hasHiddenProducts }) {
    return <section className="space-y-6 border border-gray-200 bg-white p-6"><div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Catalogo publico</p><h2 className="mt-2 text-2xl font-black uppercase tracking-[0.18em]">Productos</h2><p className="mt-2 text-sm text-gray-600">Mostrando {currentCount} de {visibleTotal} productos visibles.</p></div><form onSubmit={handleSearch} className="flex w-full max-w-xl flex-col gap-3 sm:flex-row"><input type="search" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Buscar por nombre o descripcion" className="min-w-0 flex-1 border border-black px-4 py-3 text-sm outline-none placeholder:text-gray-400 focus:bg-gray-50" /><button type="submit" className="border border-black bg-black px-6 py-3 text-[10px] font-bold uppercase tracking-[0.24em] text-white hover:bg-gray-800">Buscar</button></form></div>{hasHiddenProducts ? <div className="flex items-start gap-3 border border-gray-200 bg-gray-50 p-4"><Lock className="mt-0.5 h-4 w-4 text-black" /><p className="text-sm text-gray-600">Hay {hiddenProductsCount} productos ocultos por limite de plan.</p></div> : null}</section>;
}

function ProductGridSection({ products, pagination = [], tenant, tenantPlan, columns = 3, hiddenProductsCount = 0, hasHiddenProducts = false, isOwner = false }) {
    if (!products?.length) return <div className="border border-dashed border-gray-300 bg-gray-50 p-12 text-center"><ShoppingBag className="mx-auto mb-4 h-10 w-10 text-black" /><h3 className="text-sm font-black uppercase tracking-[0.24em]">No se encontraron productos</h3></div>;
    const hasModifiers = (product) => product.has_modifiers || (product.modifier_groups?.length > 0);
    return <div className="space-y-10"><div className={cn('grid gap-6', columns === 3 ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1 sm:grid-cols-2')}>{products.map((product) => <article key={product.id} className="group flex flex-col overflow-hidden border border-gray-200 bg-white transition hover:border-black hover:shadow-[8px_8px_0_0_rgba(0,0,0,1)]"><div className="relative aspect-[4/3] overflow-hidden border-b border-gray-200 bg-gray-50"><img src={product.image} alt={product.name} className="h-full w-full object-cover grayscale transition duration-700 group-hover:scale-105 group-hover:grayscale-0" loading="lazy" onError={(e) => { e.target.onerror = null; e.target.src = 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&h=400&fit=crop'; }} /><div className="absolute right-3 top-3 border border-black bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em]">{hasModifiers(product) && tenantPlan !== PLAN.SEMILLA ? 'Desde ' : ''}{product.formatted_price || `L. ${product.price}`}</div>{hasModifiers(product) && tenantPlan !== PLAN.SEMILLA ? <div className="absolute bottom-3 left-3 bg-black px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-white">{product.modifier_groups?.length || 0} opciones</div> : null}</div><div className="flex flex-1 flex-col gap-4 p-5"><div><h3 className="line-clamp-2 text-lg font-black uppercase tracking-[0.12em]">{product.name}</h3><p className="mt-3 min-h-[3rem] text-sm leading-6 text-gray-600">{product.description || 'Producto disponible.'}</p></div><ProductCta product={product} tenant={tenant} tenantPlan={tenantPlan} /></div></article>)}</div>{pagination.length > 3 ? <Pagination pagination={pagination} /> : null}{hasHiddenProducts ? <div className="border border-gray-200 bg-white p-5"><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Limite de plan</p><h3 className="mt-2 text-sm font-black uppercase tracking-[0.18em]">{hiddenProductsCount} productos siguen fuera del escaparate</h3><p className="mt-3 text-sm text-gray-600">Amplia el plan para publicar mas articulos sin recortar la experiencia del visitante.</p>{isOwner ? <Link href="/app" className="mt-4 inline-flex border border-black bg-black px-5 py-3 text-[10px] font-bold uppercase tracking-[0.24em] text-white hover:bg-gray-800">Administrar plan</Link> : null}</div> : null}</div>;
}

function ProductCta({ product, tenant, tenantPlan }) {
    if (tenantPlan === PLAN.SEMILLA) {
        const phone = tenant.whatsapp || tenant.whatsapp_number || '';
        const url = buildWhatsAppUrl(phone, `Hola ${tenant.name || 'equipo'}, me interesa ${product?.name || 'este producto'}.`);
        if (!isValidWhatsAppPhone(phone) || !url) return <div className="border border-gray-300 px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.24em] text-gray-400">No disponible</div>;
        return <a href={url} target="_blank" rel="noopener noreferrer" className="inline-flex w-full items-center justify-center gap-2 border border-black bg-black px-4 py-3 text-[10px] font-bold uppercase tracking-[0.24em] text-white hover:bg-gray-800"><MessageCircle className="h-4 w-4" />Consultar</a>;
    }
    return <ProductActionArea product={product} tenantId={tenant.id} tenantPlan={tenantPlan} tenantWhatsapp={tenant.whatsapp || tenant.whatsapp_number} tenantName={tenant.name} tenantSlug={tenant.slug} variant="default" className="!w-full !rounded-none !bg-black !text-white !shadow-none hover:!bg-gray-800 text-[10px] font-bold uppercase tracking-[0.24em]" />;
}

function Pagination({ pagination }) {
    return <nav className="flex flex-wrap items-center justify-center gap-2">{pagination.map((link, index) => !link.url ? <span key={`${link.label}-${index}`} className="border border-gray-200 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.18em] text-gray-300" dangerouslySetInnerHTML={{ __html: String(link.label || '').replace('&laquo;', '<<').replace('&raquo;', '>>') }} /> : <Link key={`${link.label}-${index}`} href={link.url} preserveState preserveScroll className={cn('border px-4 py-2 text-[10px] font-bold uppercase tracking-[0.18em]', link.active ? 'border-black bg-black text-white' : 'border-gray-200 bg-white hover:border-black')} dangerouslySetInnerHTML={{ __html: String(link.label || '').replace('&laquo;', '<<').replace('&raquo;', '>>') }} />)}</nav>;
}

function InfoSection({ tenant, isOpen, openingHours = [] }) {
    return <div className="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]"><div className="border border-gray-200 bg-white p-8"><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Presentacion</p><h3 className="mt-2 text-2xl font-black uppercase tracking-[0.18em]">Sobre nosotros</h3><p className="mt-6 whitespace-pre-line text-base leading-8 text-gray-600">{tenant.description || 'Bienvenido a nuestro negocio.'}</p></div><div className="space-y-6"><div className="border border-gray-200 bg-white p-6 space-y-5"><div className="flex items-start gap-4"><MapPin className="h-5 w-5 text-black" /><div><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Ubicacion</p><p className="text-sm text-gray-600">{tenant.address || 'No especificada'}</p></div></div><div className="flex items-start gap-4"><Clock className="h-5 w-5 text-black" /><div><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Estado</p><p className="text-sm text-gray-600">{isOpen ? 'Abierto ahora' : 'Cerrado'}</p></div></div>{tenant.website_url ? <div className="flex items-start gap-4"><Globe className="h-5 w-5 text-black" /><div><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Sitio web</p><a href={tenant.website_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.18em] text-black underline decoration-2 underline-offset-4 hover:text-gray-600">Visitar<ExternalLink className="h-4 w-4" /></a></div></div> : null}</div>{openingHours.length > 0 ? <div className="border border-gray-200 bg-white p-6"><p className="text-[10px] font-bold uppercase tracking-[0.24em] text-gray-500">Horarios</p><div className="mt-4 space-y-3">{openingHours.map((slot, index) => <div key={`${slot.day || slot.label || 'day'}-${index}`} className="flex items-center justify-between border-b border-gray-100 pb-3 text-xs font-bold uppercase tracking-[0.18em] text-gray-500 last:border-b-0 last:pb-0"><span className="text-black">{slot.day || slot.label || `Dia ${index + 1}`}</span><span>{slot.hours || slot.open || slot.value || 'Consultar'}</span></div>)}</div></div> : null}</div></div>;
}
