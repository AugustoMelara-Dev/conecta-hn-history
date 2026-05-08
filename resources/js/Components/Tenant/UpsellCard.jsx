import { Link } from '@inertiajs/react';
import { ArrowRight, Lock, MessageCircle, Store } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
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
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';
import { cn } from '@/lib/utils';

function getAdminRouteUrl(tenantSlug) {
    if (tenantSlug) {
        return `/app/${encodeURIComponent(tenantSlug)}/upgrade-to-pro`;
    }

    try {
        if (typeof route === 'function') {
            if (route().has('dashboard')) {
                return route('dashboard');
            }

            if (route().has('mi-cuenta.edit')) {
                return route('mi-cuenta.edit');
            }
        }
    } catch {
        return '/registrar-negocio';
    }

    return '/registrar-negocio';
}

export default function UpsellCard({
    hiddenCount = 0,
    isOwner = false,
    tenantWhatsapp = '',
    tenantName = '',
    tenantSlug = '',
    variant = 'default',
    className,
}) {
    const safeName = tenantName && typeof tenantName === 'string'
        ? tenantName.trim()
        : 'este negocio';
    const whatsappMessage = `Hola ${safeName}!

Vi su pagina en Conecta HN y quisiera conocer el menu completo.`;
    const customerWhatsAppUrl = buildWhatsAppUrl(tenantWhatsapp, whatsappMessage);
    const hasValidWhatsApp = isValidWhatsAppPhone(tenantWhatsapp);
    const adminRouteUrl = getAdminRouteUrl(tenantSlug);

    if (variant === 'inline') {
        return (
            <Alert className={cn('border-dashed', className)}>
                <Lock className="mr-2 inline size-4" aria-hidden="true" />
                <AlertTitle>{hiddenCount}+ productos adicionales</AlertTitle>
                <AlertDescription className="mt-2 flex flex-wrap items-center gap-3">
                    <span>
                        {isOwner
                            ? 'Actualiza el plan para mostrarlos en el micrositio.'
                            : 'Pregunta al negocio por mas opciones disponibles.'}
                    </span>
                    {isOwner ? (
                        <Link href={adminRouteUrl} className="font-medium text-primary hover:underline">
                            Desbloquear
                        </Link>
                    ) : hasValidWhatsApp ? (
                        <a
                            href={customerWhatsAppUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-medium text-primary hover:underline"
                        >
                            Contactar
                        </a>
                    ) : null}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Card className={cn('border-dashed', className)}>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <span className="grid size-11 place-items-center rounded-md bg-muted text-muted-foreground">
                        {isOwner ? <Lock className="size-5" aria-hidden="true" /> : <Store className="size-5" aria-hidden="true" />}
                    </span>
                    {hiddenCount > 0 && <Badge variant="secondary">+{hiddenCount}</Badge>}
                </div>
                <CardTitle>
                    {isOwner
                        ? hiddenCount === 1
                            ? 'Tienes 1 producto oculto'
                            : `Tienes ${hiddenCount} productos ocultos`
                        : 'Mas opciones disponibles'}
                </CardTitle>
                <CardDescription>
                    {isOwner
                        ? 'Tus clientes solo ven parte del catalogo publicado para este plan.'
                        : hasValidWhatsApp
                            ? 'Este negocio puede tener mas variedad disponible por WhatsApp.'
                            : 'Este negocio puede tener mas variedad disponible en su local.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-sm leading-6 text-muted-foreground">
                    {isOwner
                        ? 'Usa esta alerta como recordatorio para revisar el plan antes de publicar mas productos.'
                        : safeName}
                </p>
            </CardContent>
            <CardFooter>
                {isOwner ? (
                    <Button asChild>
                        <Link href={adminRouteUrl}>
                            Actualizar plan
                            <ArrowRight className="ml-2 size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                ) : hasValidWhatsApp ? (
                    <Button asChild>
                        <a href={customerWhatsAppUrl} target="_blank" rel="noopener noreferrer">
                            <MessageCircle className="mr-2 size-4" aria-hidden="true" />
                            Preguntar al negocio
                        </a>
                    </Button>
                ) : (
                    <Button variant="secondary" disabled>
                        Consultar en local
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}
