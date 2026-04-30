import { Link } from '@inertiajs/react';
import { CalendarClock, MessageCircle, Phone, ShoppingBag, Store } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

function MobileDockAction({ href, label, icon: Icon, external = false, inertia = false, active = false }) {
    if (!href) {
        return <div aria-hidden="true" />;
    }

    const className = cn(
        'h-16 rounded-none bg-transparent px-1 py-0 font-label text-[10px] font-semibold uppercase tracking-[0.14em] transition-colors hover:bg-muted hover:text-foreground',
        'flex-col gap-1',
        active ? 'text-foreground' : 'text-muted-foreground',
    );

    const content = (
        <>
            <Icon data-icon="dock" aria-hidden="true" />
            <span>{label}</span>
        </>
    );

    if (inertia) {
        return (
            <Button asChild variant="ghost" className={className}>
                <Link href={href}>{content}</Link>
            </Button>
        );
    }

    return (
        <Button asChild variant="ghost" className={className}>
            <a href={href} target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined}>
                {content}
            </a>
        </Button>
    );
}

export default function MicrositeMobileDock({
    primaryLabel,
    primaryHref,
    tenant,
    primaryWhatsAppUrl,
    bookingUrl,
}) {
    return (
        <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 backdrop-blur-xl md:hidden">
            <div className="grid h-16 grid-cols-4">
                <MobileDockAction href="#top" label="Inicio" icon={Store} />
                <MobileDockAction href={primaryHref} label={primaryLabel} icon={ShoppingBag} />
                {tenant.phone ? (
                    <MobileDockAction href={`tel:${tenant.phone}`} label="Llamar" icon={Phone} />
                ) : bookingUrl ? (
                    <MobileDockAction href={bookingUrl} label="Reservar" icon={CalendarClock} inertia />
                ) : (
                    <div aria-hidden="true" />
                )}
                {primaryWhatsAppUrl ? (
                    <MobileDockAction href={primaryWhatsAppUrl} label="WhatsApp" icon={MessageCircle} external active />
                ) : (
                    <div aria-hidden="true" />
                )}
            </div>
        </nav>
    );
}
