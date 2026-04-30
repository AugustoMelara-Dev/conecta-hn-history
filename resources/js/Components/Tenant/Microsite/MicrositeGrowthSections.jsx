import {
    Card,
    CardDescription,
} from '@/Components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { cn } from '@/lib/utils';

import { Eyebrow, SectionTitle } from './MicrositePrimitives';
import {
    FocusCard,
    GrowthPlaybookCard,
    JourneyStepCard,
    SignalCard,
    VerticalBlockCard,
    VerticalIntroCard,
    resolveVerticalActionTarget,
} from './MicrositeVerticalCards';

export function FocusStrip({ businessType, services, menuSections, products, bookingUrl, primaryWhatsAppUrl, profile }) {
    const configuredCards = Array.isArray(profile.focus_cards) ? profile.focus_cards : [];
    const cards = configuredCards.length > 0
        ? configuredCards.map((card) => {
            const actionHref = resolveVerticalActionTarget(card.cta_target, primaryWhatsAppUrl, bookingUrl) || '#workspace';

            return {
                title: card.title,
                copy: card.copy,
                actionLabel: card.cta_label,
                actionHref,
                external: typeof actionHref === 'string' && actionHref.startsWith('http'),
            };
        })
        : (() => {
            switch (businessType) {
                case 'food':
                    return [
                        {
                            title: 'Menú organizado',
                            copy: menuSections.length > 0
                                ? `${menuSections.length} secciones listas para que el cliente encuentre platos, combos o bebidas sin perderse.`
                                : 'Agrupa entradas, platos fuertes, bebidas y postres para vender con más claridad.',
                            actionLabel: 'Ver menú',
                            actionHref: '#catalogo',
                        },
                        {
                            title: 'Pedidos rápidos',
                            copy: 'Combina vitrina pública con contacto inmediato para captar pedidos desde móvil y escritorio.',
                            actionLabel: primaryWhatsAppUrl ? 'Pedir por WhatsApp' : 'Explorar',
                            actionHref: primaryWhatsAppUrl || '#workspace',
                            external: Boolean(primaryWhatsAppUrl),
                        },
                        {
                            title: 'Promoción del día',
                            copy: 'Usa fotos claras, secciones activas y CTA visibles para empujar tus platos estrella.',
                            actionLabel: 'Abrir catálogo',
                            actionHref: '#catalogo',
                        },
                    ];
                case 'service':
                    return [
                        {
                            title: 'Servicios claros',
                            copy: services.length > 0
                                ? `${services.length} servicios visibles con precio y duración para que el cliente decida antes de escribirte.`
                                : 'Presenta paquetes, sesiones o consultas de forma profesional y fácil de comparar.',
                            actionLabel: 'Ver servicios',
                            actionHref: '#servicios',
                        },
                        {
                            title: 'Reserva o consulta',
                            copy: 'Servicios profesionales necesitan confianza, agenda y contacto sin fricción.',
                            actionLabel: bookingUrl ? 'Reservar' : 'Consultar',
                            actionHref: bookingUrl || '#servicios',
                        },
                        {
                            title: 'Credibilidad operativa',
                            copy: 'Horarios, ubicación, equipo y reseñas quedan visibles para reducir dudas antes de cotizar.',
                            actionLabel: 'Ver información',
                            actionHref: '#informacion',
                        },
                    ];
                case 'hybrid':
                    return [
                        {
                            title: 'Oferta mixta',
                            copy: 'Un mismo negocio puede vender productos, atender servicios y sostener seguimiento comercial sin romper la experiencia.',
                            actionLabel: 'Ver ofertas',
                            actionHref: '#ofertas',
                        },
                        {
                            title: 'Catálogo + agenda',
                            copy: 'Ideal para tiendas con soporte técnico, talleres, salud estética o negocios con upsells híbridos.',
                            actionLabel: bookingUrl ? 'Reservar o consultar' : 'Hablar por WhatsApp',
                            actionHref: bookingUrl || primaryWhatsAppUrl || '#workspace',
                            external: Boolean(primaryWhatsAppUrl && !bookingUrl),
                        },
                        {
                            title: 'Experiencia coherente',
                            copy: 'Integra productos, servicios y CTA sin obligar al cliente a adivinar cómo comprar o reservar.',
                            actionLabel: profile.catalog_title,
                            actionHref: '#catalogo',
                        },
                    ];
                default:
                    return [
                        {
                            title: 'Vitrina fuerte',
                            copy: products.length > 0
                                ? `${products.length} productos visibles para descubrir, cotizar o comprar con menos pasos.`
                                : 'Arma un catálogo claro para celulares, ferretería, moda, hogar o cualquier tienda especializada.',
                            actionLabel: 'Ver catálogo',
                            actionHref: '#catalogo',
                        },
                        {
                            title: 'Contacto directo',
                            copy: 'El cliente puede avanzar rápido desde el micrositio a WhatsApp, llamada o carrito según tu plan.',
                            actionLabel: primaryWhatsAppUrl ? 'Hablar por WhatsApp' : 'Ver negocio',
                            actionHref: primaryWhatsAppUrl || '#workspace',
                            external: Boolean(primaryWhatsAppUrl),
                        },
                        {
                            title: 'Más conversión',
                            copy: 'Fotos, precios y copy comercial trabajan juntos para que la visita termine en una acción concreta.',
                            actionLabel: 'Explorar productos',
                            actionHref: '#catalogo',
                        },
                    ];
            }
        })();

    return (
        <section className="px-6 pb-8">
            <div className="mx-auto grid max-w-7xl gap-4 lg:grid-cols-3">
                {cards.map((card) => (
                    <FocusCard key={card.title} card={card} eyebrow={profile.offer_title} />
                ))}
            </div>
        </section>
    );
}

function buildVerticalSignals({
    verticalKey,
    businessType,
    productsCount,
    servicesCount,
    menuSectionsCount,
    serviceProvidersCount,
    hasBooking,
    hasWhatsApp,
    isOpen,
}) {
    switch (verticalKey || businessType) {
        case 'casual_restaurant':
        case 'restaurant':
            return [
                { label: 'Menú', value: menuSectionsCount || 'Listo', helper: 'secciones activas' },
                { label: 'Platos', value: productsCount, helper: 'items visibles' },
                { label: 'Pedidos', value: hasWhatsApp ? 'Directo' : 'Catálogo', helper: 'canal rápido' },
            ];
        case 'coffee_shop':
            return [
                { label: 'Carta', value: menuSectionsCount || 'Lista', helper: 'bloques visibles' },
                { label: 'Brunch', value: productsCount, helper: 'items destacados' },
                { label: 'Pickup', value: hasWhatsApp ? 'Activo' : 'Manual', helper: 'pedido rápido' },
            ];
        case 'premium_phone_store':
        case 'cellphone_store':
            return [
                { label: 'Equipos', value: productsCount, helper: 'catálogo visible' },
                { label: 'Bundles', value: productsCount > 1 ? 'Activos' : 'Base', helper: 'upsell listo' },
                { label: 'Soporte', value: hasWhatsApp ? 'Listo' : 'Manual', helper: 'canal posventa' },
            ];
        case 'hardware_store':
            return [
                { label: 'Materiales', value: productsCount, helper: 'catálogo activo' },
                { label: 'Cotiza', value: hasWhatsApp ? 'Rápido' : 'Manual', helper: 'cierre comercial' },
                { label: 'Operación', value: isOpen ? 'Hoy' : 'Cerrado', helper: 'tienda disponible' },
            ];
        case 'dental_clinic':
        case 'clinic':
            return [
                { label: 'Agenda', value: hasBooking ? 'Activa' : 'Manual', helper: 'recepción visible' },
                { label: 'Servicios', value: servicesCount, helper: 'consultas visibles' },
                { label: 'Equipo', value: serviceProvidersCount || 'Staff', helper: 'especialistas visibles' },
            ];
        case 'corporate_law_firm':
        case 'law_firm':
            return [
                { label: 'Consultas', value: servicesCount, helper: 'servicios legales' },
                { label: 'Despacho', value: hasBooking ? 'Agenda' : 'Contacto', helper: 'primer filtro' },
                { label: 'Atención', value: hasWhatsApp ? 'Directa' : 'Manual', helper: 'respuesta inicial' },
            ];
        case 'financial_consultancy':
        case 'consultancy':
            return [
                { label: 'Discovery', value: hasBooking ? 'Activa' : 'Lead', helper: 'entrada consultiva' },
                { label: 'Servicios', value: servicesCount, helper: 'paquetes visibles' },
                { label: 'Funnel', value: hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'seguimiento comercial' },
            ];
        case 'auto_parts_store':
        case 'workshop':
            return [
                { label: 'Diagnósticos', value: servicesCount, helper: 'servicios técnicos' },
                { label: 'Repuestos', value: productsCount, helper: 'catálogo visible' },
                { label: 'Ingreso', value: hasWhatsApp ? 'Directo' : 'Manual', helper: 'primer contacto' },
            ];
        case 'premium_barbershop':
        case 'spa_studio':
        case 'barbershop':
            return [
                { label: 'Agenda', value: hasBooking ? 'Activa' : 'Manual', helper: 'reservas abiertas' },
                { label: 'Servicios', value: servicesCount, helper: 'bloques visibles' },
                { label: 'Equipo', value: serviceProvidersCount, helper: 'especialistas visibles' },
            ];
        case 'hybrid':
            return [
                { label: 'Servicios', value: servicesCount, helper: 'frente consultivo' },
                { label: 'Catálogo', value: productsCount, helper: 'items visibles' },
                { label: 'Canal', value: hasBooking ? 'Reserva' : hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'entrada principal' },
            ];
        case 'service':
            return [
                { label: 'Servicios', value: servicesCount, helper: 'bloques visibles' },
                { label: 'Equipo', value: serviceProvidersCount, helper: 'personas visibles' },
                { label: 'Canal', value: hasBooking ? 'Reserva' : hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'entrada principal' },
            ];
        default:
            return [
                { label: 'Catálogo', value: productsCount, helper: 'items visibles' },
                { label: 'Canal', value: hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'contacto principal' },
                { label: 'Estado', value: isOpen ? 'Abierto' : 'Cerrado', helper: 'situación actual' },
            ];
    }
}

export function VerticalPlaybookSection({
    profile,
    businessType,
    productsCount,
    servicesCount,
    menuSectionsCount,
    serviceProvidersCount,
    primaryWhatsAppUrl,
    bookingUrl,
    isOpen,
}) {
    const journeySteps = Array.isArray(profile.journey_steps) ? profile.journey_steps : [];
    const growthCards = Array.isArray(profile.growth_cards) ? profile.growth_cards : [];
    const signals = buildVerticalSignals({
        verticalKey: profile.vertical_key,
        businessType,
        productsCount,
        servicesCount,
        menuSectionsCount,
        serviceProvidersCount,
        hasBooking: Boolean(bookingUrl),
        hasWhatsApp: Boolean(primaryWhatsAppUrl),
        isOpen,
    });

    if (journeySteps.length === 0 && growthCards.length === 0 && signals.length === 0) {
        return null;
    }

    return (
        <section className="px-6 pb-10" data-testid="vertical-playbook">
            <div className="mx-auto grid max-w-7xl gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <Card className="overflow-hidden rounded-[2rem] border-0 bg-foreground p-8 text-background shadow-2xl" data-testid="vertical-journey">
                    <Eyebrow dark>Playbook del vertical</Eyebrow>
                    <SectionTitle dark className="mt-3 text-4xl">
                        {profile.journey_title || 'Cómo convierte este negocio'}
                    </SectionTitle>
                    <CardDescription className="mt-4 max-w-3xl text-base leading-8 text-background/75">
                        {profile.journey_copy || 'La vitrina pública ya aterriza mejor el camino desde el interés hasta la acción.'}
                    </CardDescription>

                    {journeySteps.length > 0 ? (
                        <div className="mt-8 grid gap-4 lg:grid-cols-3">
                            {journeySteps.map((step) => (
                                <JourneyStepCard key={step.title} step={step} />
                            ))}
                        </div>
                    ) : null}
                </Card>

                <div className="flex flex-col gap-4">
                    {signals.length > 0 ? (
                        <div className="grid gap-4 sm:grid-cols-3 xl:grid-cols-1" data-testid="vertical-signal-grid">
                            {signals.map((signal) => (
                                <SignalCard key={signal.label} signal={signal} />
                            ))}
                        </div>
                    ) : null}

                    {growthCards.length > 0 ? (
                        <Card className="rounded-[1.9rem] bg-muted p-6">
                            <Eyebrow>Crecimiento</Eyebrow>
                            <SectionTitle className="mt-3 text-3xl">
                                {profile.growth_title || 'Palancas de crecimiento'}
                            </SectionTitle>
                            <CardDescription className="mt-3 leading-7">
                                {profile.growth_copy || 'Cada vertical necesita una narrativa distinta para vender mejor.'}
                            </CardDescription>

                            <div className="mt-6 flex flex-col gap-4">
                                {growthCards.map((card) => (
                                    <GrowthPlaybookCard
                                        key={card.title}
                                        card={card}
                                        primaryWhatsAppUrl={primaryWhatsAppUrl}
                                        bookingUrl={bookingUrl}
                                    />
                                ))}
                            </div>
                        </Card>
                    ) : null}
                </div>
            </div>
        </section>
    );
}

export function VerticalShowcaseSection({ profile, primaryWhatsAppUrl, bookingUrl }) {
    const blocks = Array.isArray(profile.specialized_blocks) ? profile.specialized_blocks : [];
    const trustPoints = Array.isArray(profile.trust_points) ? profile.trust_points : [];

    if (blocks.length === 0 && trustPoints.length === 0) {
        return null;
    }

    return (
        <section className="px-6 pb-8" data-testid="vertical-showcase">
            <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
                <VerticalIntroCard profile={profile} trustPoints={trustPoints} />

                <div className="grid gap-4 xl:grid-cols-2">
                    {blocks.map((block) => (
                        <VerticalBlockCard
                            key={block.title}
                            block={block}
                            profile={profile}
                            primaryWhatsAppUrl={primaryWhatsAppUrl}
                            bookingUrl={bookingUrl}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}

export function WorkspaceTabs({ tabs, activeTab, onChange }) {
    return (
        <div className="sticky top-16 z-30 border-b border-border bg-background/92 backdrop-blur-xl" data-testid="storefront-tabs">
            <div className="mx-auto max-w-7xl px-6 py-4">
                <Tabs value={activeTab} onValueChange={onChange}>
                    <TabsList className="h-auto flex-wrap rounded-full p-1" aria-label="Secciones del micrositio">
                        {tabs.map((tab) => (
                            <TabsTrigger
                                key={tab.id}
                                value={tab.id}
                                className={cn(
                                    'rounded-full px-4 py-3 font-label text-[11px] font-semibold uppercase tracking-[0.18em]',
                                    'data-[state=active]:shadow-sm'
                                )}
                            >
                                {tab.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            </div>
        </div>
    );
}
