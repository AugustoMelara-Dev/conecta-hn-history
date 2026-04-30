import { Link } from '@inertiajs/react';
import ReviewSection from '@/Components/Public/ReviewSection';
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
import { Input } from '@/Components/ui/input';
import {
    CalendarClock,
    Clock3,
    ExternalLink,
    Globe,
    Lock,
    MapPin,
    MessageCircle,
    Phone,
    Search,
    ShoppingBag,
} from 'lucide-react';

import { Eyebrow, SectionTitle } from './MicrositePrimitives';
import {
    MicrositeEmptyState,
    PaginationBar,
    StorefrontProductCard,
} from './MicrositeProductCards';

function SearchToolbar({
    title,
    copy,
    searchQuery,
    setSearchQuery,
    handleSearch,
    visibleTotal,
    currentCount,
    hiddenProductsCount,
    hasHiddenProducts,
}) {
    return (
        <section id="catalogo">
            <Card className="rounded-[1.9rem] border-0 bg-muted shadow-none">
                <CardHeader className="gap-6 p-6 md:p-8 xl:flex-row xl:items-end xl:justify-between">
                    <div className="max-w-2xl">
                        <Eyebrow>{title}</Eyebrow>
                        <SectionTitle className="mt-3 text-3xl">{title}</SectionTitle>
                        <CardDescription className="mt-3 text-base leading-8">
                            {copy}
                        </CardDescription>
                    </div>

                    <form onSubmit={handleSearch} className="w-full max-w-md">
                        <label className="sr-only" htmlFor="catalog-search">
                            Buscar
                        </label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="catalog-search"
                                name="catalog-search"
                                type="search"
                                autoComplete="off"
                                value={searchQuery}
                                onChange={(event) => setSearchQuery(event.target.value)}
                                placeholder="Busca productos, platos o palabras clave"
                                className="h-auto rounded-full bg-background px-12 py-3 text-sm"
                            />
                        </div>
                    </form>
                </CardHeader>

                <CardContent className="flex flex-wrap gap-3 px-6 pb-6 md:px-8 md:pb-8">
                    <Badge variant="secondary" className="rounded-full px-4 py-2 font-label text-xs uppercase tracking-[0.16em]">
                        {currentCount} visibles ahora
                    </Badge>
                    <Badge variant="secondary" className="rounded-full px-4 py-2 font-label text-xs uppercase tracking-[0.16em]">
                        {visibleTotal} disponibles
                    </Badge>
                    {hasHiddenProducts ? (
                        <Badge className="rounded-full px-4 py-2 font-label text-xs uppercase tracking-[0.16em]">
                            <Lock data-icon="inline-start" aria-hidden="true" />
                            {hiddenProductsCount} ocultos por plan o visibilidad
                        </Badge>
                    ) : null}
                </CardContent>
            </Card>
        </section>
    );
}

function MenuOverviewSection({ menuSections = [] }) {
    if (menuSections.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-3 md:grid-cols-3">
            {menuSections.map((section) => (
                <Card key={section.id} className="rounded-[1.4rem] shadow-sm">
                    <CardHeader>
                        <Eyebrow>Sección activa</Eyebrow>
                        <CardTitle className="font-headline text-xl">
                            {section.name}
                        </CardTitle>
                        <CardDescription className="leading-7">
                            {section.products_count} productos visibles en esta parte del menú.
                        </CardDescription>
                    </CardHeader>
                </Card>
            ))}
        </div>
    );
}

function ProductPanel({
    businessType,
    profile,
    panelTitle,
    panelCopy,
    searchQuery,
    setSearchQuery,
    handleSearch,
    products,
    pagination,
    tenant,
    tenantPlan,
    hiddenProductsCount,
    hasHiddenProducts,
    visibleTotal,
    menuSections,
}) {
    const emptyMessage = businessType === 'service'
        ? 'Este negocio prioriza servicios. Si también vende complementos, aparecerán aquí.'
        : businessType === 'food'
            ? 'Todavía no hay platos visibles en el menú público.'
            : 'Todavía no hay productos visibles en esta vitrina.';

    return (
        <div className="flex flex-col gap-8" data-testid="product-panel">
            <SearchToolbar
                title={panelTitle || profile.catalog_panel_title || profile.catalog_title}
                copy={panelCopy || profile.catalog_panel_copy || profile.catalog_copy}
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                handleSearch={handleSearch}
                visibleTotal={visibleTotal}
                currentCount={products.length}
                hiddenProductsCount={hiddenProductsCount}
                hasHiddenProducts={hasHiddenProducts}
            />

            {(businessType === 'food' || businessType === 'hybrid') && menuSections.length > 0 ? (
                <MenuOverviewSection menuSections={menuSections} />
            ) : null}

            {products.length > 0 ? (
                <>
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {products.map((product) => (
                            <StorefrontProductCard
                                key={product.id}
                                product={product}
                                tenant={tenant}
                                tenantPlan={tenantPlan}
                            />
                        ))}
                    </div>

                    <PaginationBar pagination={pagination} />
                </>
            ) : (
                <MicrositeEmptyState
                    icon={ShoppingBag}
                    title="Sin catálogo público por ahora"
                    description={emptyMessage}
                />
            )}
        </div>
    );
}

function ServiceStatBadge({ children }) {
    return (
        <Badge variant="secondary" className="rounded-full px-4 py-2 font-label text-xs uppercase tracking-[0.16em]">
            {children}
        </Badge>
    );
}

function ServiceCard({ service, bookingUrl, primaryWhatsAppUrl }) {
    return (
        <Card className="rounded-[1.7rem] p-6 shadow-sm">
            <Eyebrow>Servicio destacado</Eyebrow>
            <div className="mt-3 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0">
                    <CardTitle className="font-headline text-2xl">
                        {service.name}
                    </CardTitle>
                    <CardDescription className="mt-3 leading-7">
                        {service.description || 'Atención profesional lista para reservar o cotizar.'}
                    </CardDescription>
                </div>

                <div className="shrink-0 rounded-[1.25rem] bg-primary px-4 py-3 text-primary-foreground">
                    <Eyebrow dark>Precio</Eyebrow>
                    <p className="mt-2 font-headline text-xl font-bold">
                        {service.formatted_price}
                    </p>
                    <p className="mt-1 text-sm text-primary-foreground/75">
                        {service.duration_formatted || `${service.duration_minutes} min`}
                    </p>
                </div>
            </div>

            <div className="mt-5 flex flex-wrap gap-3">
                {bookingUrl ? (
                    <Button
                        asChild
                        className="h-auto rounded-full px-5 py-3 font-label text-xs font-semibold uppercase tracking-[0.16em]"
                    >
                        <Link href={`${bookingUrl}?service=${service.id}`}>
                            <CalendarClock data-icon="inline-start" aria-hidden="true" />
                            Reservar
                        </Link>
                    </Button>
                ) : null}

                {primaryWhatsAppUrl ? (
                    <Button
                        asChild
                        variant="outline"
                        className="h-auto rounded-full px-5 py-3 font-label text-xs font-semibold uppercase tracking-[0.16em]"
                    >
                        <a href={primaryWhatsAppUrl} target="_blank" rel="noopener noreferrer">
                            <MessageCircle data-icon="inline-start" aria-hidden="true" />
                            Consultar
                        </a>
                    </Button>
                ) : null}
            </div>
        </Card>
    );
}

function ServicePanel({ services, bookingUrl, serviceProvidersCount, primaryWhatsAppUrl, profile }) {
    return (
        <section id="servicios" className="flex flex-col gap-8" data-testid="service-panel">
            <Card className="rounded-[1.9rem] border-0 bg-muted p-6 shadow-none md:p-8">
                <Eyebrow>Servicios</Eyebrow>
                <SectionTitle className="mt-3 text-3xl">
                    {profile?.service_panel_title || 'Reserva, cotiza o consulta con claridad'}
                </SectionTitle>
                <CardDescription className="mt-3 max-w-3xl text-base leading-8">
                    {profile?.service_panel_copy || 'Servicios visibles, duración, precio y una forma directa de avanzar a cita o conversación.'}
                </CardDescription>
                <div className="mt-6 flex flex-wrap gap-3">
                    <ServiceStatBadge>
                        {services.length} servicios activos
                    </ServiceStatBadge>
                    <ServiceStatBadge>
                        {serviceProvidersCount} personas visibles
                    </ServiceStatBadge>
                </div>
            </Card>

            {services.length > 0 ? (
                <div className="grid gap-5 xl:grid-cols-2">
                    {services.map((service) => (
                        <ServiceCard
                            key={service.id}
                            service={service}
                            bookingUrl={bookingUrl}
                            primaryWhatsAppUrl={primaryWhatsAppUrl}
                        />
                    ))}
                </div>
            ) : (
                <MicrositeEmptyState
                    icon={CalendarClock}
                    title="Todavía no hay servicios visibles"
                    description="Cuando el negocio publique sus servicios, esta sección se convertirá en su frente de reservas y consultas."
                />
            )}
        </section>
    );
}

function OffersPanel({ businessType, services, menuSections, products, bookingUrl, primaryWhatsAppUrl }) {
    const cards = [
        {
            title: businessType === 'service' ? 'Servicios visibles' : 'Productos visibles',
            value: businessType === 'service' ? services.length : products.length,
            copy: businessType === 'service'
                ? 'Presentación clara para que el cliente entienda el alcance antes de reservar.'
                : 'Catálogo público listo para mover ventas, consultas o pedidos.',
        },
        {
            title: 'Secciones activas',
            value: menuSections.length,
            copy: 'Útil para restaurantes o negocios que necesitan organizar su oferta por bloques.',
        },
        {
            title: 'Agenda y contacto',
            value: bookingUrl ? 'Listo' : primaryWhatsAppUrl ? 'WhatsApp' : 'Manual',
            copy: 'Canales directos para convertir interés en reserva, pedido o conversación.',
        },
    ];

    return (
        <section id="ofertas" className="flex flex-col gap-8">
            <div className="grid gap-4 lg:grid-cols-3">
                {cards.map((card) => (
                    <Card key={card.title} className="rounded-[1.7rem] p-6 shadow-sm">
                        <Eyebrow>Oferta principal</Eyebrow>
                        <CardTitle className="mt-3 font-headline text-xl">
                            {card.title}
                        </CardTitle>
                        <p className="mt-4 font-headline text-3xl font-extrabold tracking-tight text-foreground">
                            {card.value}
                        </p>
                        <CardDescription className="mt-3 leading-7">{card.copy}</CardDescription>
                    </Card>
                ))}
            </div>

            {services.length > 0 ? (
                <Card className="rounded-[1.8rem] bg-muted p-6">
                    <Eyebrow>Servicios visibles</Eyebrow>
                    <SectionTitle className="mt-3 text-2xl">La parte consultiva del negocio</SectionTitle>
                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                        {services.slice(0, 4).map((service) => (
                            <Card key={service.id} className="rounded-[1.35rem] p-5 shadow-none">
                                <CardTitle className="font-headline text-lg">
                                    {service.name}
                                </CardTitle>
                                <CardDescription className="mt-2 leading-7">
                                    {service.description || 'Servicio profesional disponible.'}
                                </CardDescription>
                                <p className="mt-4 text-sm font-semibold text-foreground">
                                    {service.formatted_price} - {service.duration_formatted || `${service.duration_minutes} min`}
                                </p>
                            </Card>
                        ))}
                    </div>
                </Card>
            ) : null}
        </section>
    );
}

function InfoRow({ icon: Icon, label, children }) {
    return (
        <div className="flex items-start gap-4">
            <Icon data-icon="info" className="mt-1 size-4 text-foreground" aria-hidden="true" />
            <div className="min-w-0">
                <p className="font-semibold text-foreground">{label}</p>
                <div className="mt-1 leading-7">{children}</div>
            </div>
        </div>
    );
}

function InfoPanel({ tenant, isOpen, openingHours = [] }) {
    return (
        <section id="informacion" className="grid gap-8 lg:grid-cols-[minmax(0,1.65fr)_360px]">
            <Card className="rounded-[1.9rem] border-0 bg-muted p-8 shadow-none md:p-10">
                <Eyebrow>Presentación</Eyebrow>
                <SectionTitle className="mt-3 text-3xl">Sobre {tenant.name}</SectionTitle>
                <CardDescription className="mt-6 whitespace-pre-line text-base leading-8">
                    {tenant.about || tenant.description || 'Bienvenido a este negocio dentro de Vito Business OS.'}
                </CardDescription>
            </Card>

            <div className="flex flex-col gap-6">
                <Card className="rounded-[1.6rem] p-6 shadow-sm">
                    <Eyebrow>Información clave</Eyebrow>
                    <div className="mt-5 flex flex-col gap-5 text-sm text-muted-foreground">
                        <InfoRow icon={MapPin} label="Ubicación">
                            <p>{tenant.address || tenant.location?.address || 'No especificada'}</p>
                        </InfoRow>

                        <InfoRow icon={Clock3} label="Estado">
                            <p>{isOpen ? 'Abierto ahora' : 'Cerrado en este momento'}</p>
                        </InfoRow>

                        {tenant.phone ? (
                            <InfoRow icon={Phone} label="Teléfono">
                                <a href={`tel:${tenant.phone}`} className="inline-flex underline decoration-border underline-offset-4 transition-colors hover:text-foreground">
                                    {tenant.phone}
                                </a>
                            </InfoRow>
                        ) : null}

                        {tenant.website_url ? (
                            <InfoRow icon={Globe} label="Sitio web">
                                <a
                                    href={tenant.website_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-2 underline decoration-border underline-offset-4 transition-colors hover:text-foreground"
                                >
                                    Visitar
                                    <ExternalLink data-icon="inline-end" aria-hidden="true" />
                                </a>
                            </InfoRow>
                        ) : null}
                    </div>
                </Card>

                {openingHours.length > 0 ? (
                    <Card className="rounded-[1.6rem] border-0 bg-foreground p-6 text-background">
                        <Eyebrow dark>Horarios</Eyebrow>
                        <div className="mt-5 flex flex-col gap-4">
                            {openingHours.map((slot) => (
                                <div key={`${slot.day_name}-${slot.day_of_week}`} className="flex items-center justify-between gap-4 text-sm">
                                    <span className="font-semibold">
                                        {slot.day_name}
                                    </span>
                                    <span className="text-background/70">
                                        {slot.formatted}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Card>
                ) : null}
            </div>
        </section>
    );
}

function ReviewsPanel({ tenant }) {
    return (
        <section className="rounded-[1.9rem] bg-muted p-6 md:p-8">
            <ReviewSection tenant={tenant} reviews={tenant.reviews || []} />
        </section>
    );
}

export default function MicrositeWorkspace({
    activeTab,
    businessType,
    services,
    menuSections,
    products,
    bookingUrl,
    primaryWhatsAppUrl,
    profile,
    searchQuery,
    setSearchQuery,
    handleSearch,
    pagination,
    tenant,
    tenantPlan,
    hiddenProductsCount,
    hasHiddenProducts,
    visibleTotal,
    serviceProvidersCount,
    isOpen,
    openingHours,
}) {
    return (
        <main id="workspace" className="mx-auto flex max-w-7xl flex-col gap-10 px-6 py-10 pb-32">
            {activeTab === 'offers' ? (
                <OffersPanel
                    businessType={businessType}
                    services={services}
                    menuSections={menuSections}
                    products={products}
                    bookingUrl={bookingUrl}
                    primaryWhatsAppUrl={primaryWhatsAppUrl}
                />
            ) : null}

            {activeTab === 'catalog' ? (
                <ProductPanel
                    businessType={businessType}
                    profile={profile}
                    panelTitle={profile.catalog_panel_title}
                    panelCopy={profile.catalog_panel_copy}
                    searchQuery={searchQuery}
                    setSearchQuery={setSearchQuery}
                    handleSearch={handleSearch}
                    products={products}
                    pagination={pagination}
                    tenant={tenant}
                    tenantPlan={tenantPlan}
                    hiddenProductsCount={hiddenProductsCount}
                    hasHiddenProducts={hasHiddenProducts}
                    visibleTotal={visibleTotal}
                    menuSections={menuSections}
                />
            ) : null}

            {activeTab === 'services' ? (
                <ServicePanel
                    services={services}
                    bookingUrl={bookingUrl}
                    serviceProvidersCount={serviceProvidersCount}
                    primaryWhatsAppUrl={primaryWhatsAppUrl}
                    profile={profile}
                />
            ) : null}

            {activeTab === 'info' ? (
                <InfoPanel tenant={tenant} isOpen={isOpen} openingHours={openingHours} />
            ) : null}

            {activeTab === 'reviews' ? (
                <section id="resenas">
                    <ReviewsPanel tenant={tenant} />
                </section>
            ) : null}
        </main>
    );
}
