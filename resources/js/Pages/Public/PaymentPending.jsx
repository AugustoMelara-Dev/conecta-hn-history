import { useCallback, useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Clock3,
    ExternalLink,
    HelpCircle,
    LoaderCircle,
    ReceiptText,
} from 'lucide-react';

import PublicLayout from '@/Layouts/PublicLayout';
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
import { Progress } from '@/Components/ui/progress';
import { Separator } from '@/Components/ui/separator';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('es-HN', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const STATUS_META = {
    pending: {
        title: 'Verificando tu pago',
        description: 'Esto puede tomar unos minutos. Puedes dejar esta pagina abierta mientras confirmamos la transaccion.',
        badge: 'Pago en revision',
        variant: 'secondary',
        icon: Clock3,
        progress: 66,
    },
    completed: {
        title: 'Pago confirmado',
        description: 'Tu suscripcion fue activada correctamente. Te llevaremos a tu panel en unos segundos.',
        badge: 'Confirmado',
        variant: 'default',
        icon: CheckCircle2,
        progress: 100,
    },
    paid: {
        title: 'Pago confirmado',
        description: 'Tu suscripcion fue activada correctamente. Te llevaremos a tu panel en unos segundos.',
        badge: 'Confirmado',
        variant: 'default',
        icon: CheckCircle2,
        progress: 100,
    },
    approved: {
        title: 'Pago confirmado',
        description: 'Tu suscripcion fue activada correctamente. Te llevaremos a tu panel en unos segundos.',
        badge: 'Confirmado',
        variant: 'default',
        icon: CheckCircle2,
        progress: 100,
    },
    failed: {
        title: 'Pago no procesado',
        description: 'No pudimos confirmar este pago. Revisa los datos o contacta a soporte para que podamos ayudarte.',
        badge: 'Requiere atencion',
        variant: 'destructive',
        icon: AlertCircle,
        progress: 100,
    },
    rejected: {
        title: 'Pago no procesado',
        description: 'No pudimos confirmar este pago. Revisa los datos o contacta a soporte para que podamos ayudarte.',
        badge: 'Requiere atencion',
        variant: 'destructive',
        icon: AlertCircle,
        progress: 100,
    },
};

function normalizeStatus(value) {
    const normalized = String(value || 'pending').toLowerCase();
    return STATUS_META[normalized] ? normalized : 'pending';
}

function formatElapsed(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function TimelineStep({ active, complete, description, index, label }) {
    return (
        <li className="grid grid-cols-[auto_1fr] gap-3">
            <div
                className={[
                    'flex size-9 items-center justify-center rounded-full border text-sm font-semibold',
                    complete
                        ? 'border-primary bg-primary text-primary-foreground'
                        : active
                            ? 'border-ring bg-secondary text-secondary-foreground'
                            : 'border-border bg-muted text-muted-foreground',
                ].join(' ')}
                aria-hidden="true"
            >
                {complete ? <CheckCircle2 /> : index}
            </div>
            <div className="min-w-0 pt-1">
                <p className="text-sm font-medium text-foreground">{label}</p>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </li>
    );
}

function PaymentTimeline({ status }) {
    const currentIndex = ['completed', 'paid', 'approved'].includes(status)
        ? 3
        : ['failed', 'rejected'].includes(status)
            ? 2
            : 2;

    const steps = [
        { label: 'Orden registrada', description: 'Recibimos la referencia del pago.' },
        { label: 'Revision de pago', description: 'Estamos verificando la transaccion.' },
        { label: 'Activacion', description: 'Tu panel queda listo al confirmar.' },
    ];

    return (
        <ol className="flex flex-col gap-4" aria-label="Estado del pago">
            {steps.map((step, index) => (
                <TimelineStep
                    key={step.label}
                    index={index + 1}
                    label={step.label}
                    description={step.description}
                    active={index + 1 === currentIndex}
                    complete={index + 1 < currentIndex || ['completed', 'paid', 'approved'].includes(status)}
                />
            ))}
        </ol>
    );
}

function PaymentSummary({ payment }) {
    if (!payment) return null;

    return (
        <Card className="bg-muted/40" data-testid="public-payment-summary">
            <CardContent className="flex flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <span className="text-sm text-muted-foreground">Monto</span>
                    <span className="text-right text-base font-semibold text-foreground">
                        {currencyFormatter.format(Number(payment.amount) || 0)}
                    </span>
                </div>
                {payment.method ? (
                    <>
                        <Separator />
                        <div className="flex items-start justify-between gap-4">
                            <span className="text-sm text-muted-foreground">Metodo</span>
                            <span className="text-right text-sm font-medium text-foreground">{payment.method}</span>
                        </div>
                    </>
                ) : null}
                <Separator />
                <div className="flex items-start justify-between gap-4">
                    <span className="text-sm text-muted-foreground">Referencia</span>
                    <span className="text-right font-mono text-sm text-foreground">#{payment.id}</span>
                </div>
                {payment.created_at ? (
                    <>
                        <Separator />
                        <div className="flex items-start justify-between gap-4">
                            <span className="text-sm text-muted-foreground">Registrado</span>
                            <span className="text-right text-sm text-foreground">
                                {dateFormatter.format(new Date(payment.created_at))}
                            </span>
                        </div>
                    </>
                ) : null}
            </CardContent>
        </Card>
    );
}

export default function PaymentPending({ payment, tenant, checkUrl }) {
    const { auth } = usePage().props;
    const [status, setStatus] = useState(() => normalizeStatus(payment?.status));
    const [elapsedTime, setElapsedTime] = useState(0);
    const panelUrl = auth?.panel_url || auth?.user?.panel_url || '/registrar-negocio';
    const billingUrl = panelUrl.startsWith('/app/') ? `${panelUrl}/manage-billing` : panelUrl;
    const statusMeta = STATUS_META[status];
    const StatusIcon = statusMeta.icon;
    const isPending = status === 'pending';
    const isComplete = ['completed', 'paid', 'approved'].includes(status);
    const isFailed = ['failed', 'rejected'].includes(status);

    useEffect(() => {
        if (!isPending) return undefined;

        const pollInterval = window.setInterval(() => {
            fetch(checkUrl || `/api/payments/${payment?.id}/status`, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.status && normalizeStatus(data.status) !== 'pending') {
                        setStatus(normalizeStatus(data.status));
                    }
                })
                .catch(() => {});
        }, 5000);

        return () => window.clearInterval(pollInterval);
    }, [checkUrl, isPending, payment?.id]);

    useEffect(() => {
        const timer = window.setInterval(() => {
            setElapsedTime((previous) => previous + 1);
        }, 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (!isComplete) return undefined;

        const redirectTimer = window.setTimeout(() => {
            router.visit(panelUrl);
        }, 2000);

        return () => window.clearTimeout(redirectTimer);
    }, [isComplete, panelUrl]);

    const handleRefresh = useCallback(() => {
        router.reload({ only: ['payment'] });
    }, []);

    return (
        <PublicLayout>
            <Head>
                <title>Procesando pago | Dovra AI</title>
                <meta name="robots" content="noindex" />
            </Head>

            <main className="min-h-screen bg-background px-4 py-12" data-testid="public-payment-pending-page">
                <div className="mx-auto flex max-w-2xl flex-col gap-6">
                    <Card data-testid="public-payment-pending-card">
                        <CardHeader className="items-center gap-5 text-center">
                            <div className="flex size-16 items-center justify-center rounded-full bg-secondary text-secondary-foreground">
                                {isPending ? (
                                    <LoaderCircle className="animate-spin" aria-hidden="true" />
                                ) : (
                                    <StatusIcon aria-hidden="true" />
                                )}
                            </div>
                            <div className="flex flex-col items-center gap-3">
                                <Badge variant={statusMeta.variant}>{statusMeta.badge}</Badge>
                                <div className="flex flex-col gap-2">
                                    <CardTitle>{statusMeta.title}</CardTitle>
                                    <CardDescription>{statusMeta.description}</CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-6">
                            <div aria-live="polite" className="flex flex-col gap-2">
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="text-muted-foreground">Progreso</span>
                                    <span className="font-medium text-foreground">
                                        {isPending ? `Tiempo: ${formatElapsed(elapsedTime)}` : statusMeta.badge}
                                    </span>
                                </div>
                                <Progress value={statusMeta.progress} aria-label="Progreso de verificacion del pago" />
                            </div>

                            <PaymentSummary payment={payment} />
                            <PaymentTimeline status={status} />

                            {tenant?.name ? (
                                <Alert>
                                    <ReceiptText aria-hidden="true" />
                                    <AlertTitle>Negocio en activacion</AlertTitle>
                                    <AlertDescription>
                                        Este pago esta asociado a {tenant.name}. Cuando se confirme, el panel quedara disponible con las funciones del plan correspondiente.
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            {isFailed ? (
                                <Alert variant="destructive">
                                    <AlertCircle aria-hidden="true" />
                                    <AlertTitle>Necesitamos revisar este pago</AlertTitle>
                                    <AlertDescription>
                                        Si el cargo aparece en tu banco, contacta a soporte con la referencia para evitar duplicar el pago.
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                        </CardContent>

                        <CardFooter className="flex flex-col gap-3 sm:flex-row">
                            {isComplete ? (
                                <Button asChild className="w-full sm:w-auto">
                                    <Link href={panelUrl}>
                                        Ir a mi panel
                                        <ExternalLink data-icon="inline-end" aria-hidden="true" />
                                    </Link>
                                </Button>
                            ) : null}

                            {isFailed ? (
                                <Button asChild className="w-full sm:w-auto">
                                    <Link href={billingUrl}>Intentar de nuevo</Link>
                                </Button>
                            ) : null}

                            {isPending ? (
                                <Button type="button" className="w-full sm:w-auto" onClick={handleRefresh}>
                                    Revisar estado
                                </Button>
                            ) : null}

                            <Button type="button" variant="outline" className="w-full sm:w-auto" disabled>
                                <HelpCircle data-icon="inline-start" aria-hidden="true" />
                                Contacta soporte desde tu panel
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </main>
        </PublicLayout>
    );
}
