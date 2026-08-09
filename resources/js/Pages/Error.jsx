import { Head, Link, usePage } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';

const messages = {
    403: ['Acceso restringido', 'Tu cuenta no tiene permiso para abrir esta página.'],
    404: ['Página no encontrada', 'La dirección cambió o el contenido ya no está disponible.'],
    419: ['Sesión vencida', 'Actualiza la página e inicia sesión nuevamente.'],
    429: ['Demasiadas solicitudes', 'Espera un momento antes de volver a intentarlo.'],
    500: ['No pudimos cargar la página', 'El equipo recibió el incidente. Puedes volver al inicio o reintentar.'],
    503: ['Servicio temporalmente no disponible', 'Estamos realizando una tarea de mantenimiento.'],
};

export default function Error({ status = 500 }) {
    const { auth } = usePage().props;
    const [title, description] = messages[status] || messages[500];
    const panelUrl = auth?.panel_url || auth?.user?.panel_url;

    return (
        <>
            <Head title={`${status} · ${title}`} />
            <main className="grid min-h-screen place-items-center bg-background px-4 py-12 text-foreground">
                <section className="w-full max-w-xl rounded-xl border bg-card p-6 shadow-sm sm:p-10" aria-labelledby="error-title">
                    <p className="font-mono text-sm font-semibold tabular-nums text-muted-foreground">Error {status}</p>
                    <h1 id="error-title" className="mt-3 font-display text-3xl font-bold tracking-tight sm:text-4xl">{title}</h1>
                    <p className="mt-4 max-w-prose leading-7 text-muted-foreground">{description}</p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Button asChild><Link href="/">Volver al inicio</Link></Button>
                        {panelUrl && <Button variant="outline" asChild><a href={panelUrl}>Abrir mi panel</a></Button>}
                    </div>
                </section>
            </main>
        </>
    );
}
