import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

function normalizePaymentStatus(rawStatus) {
    if (rawStatus === 'approved' || rawStatus === 'completed' || rawStatus === 'paid') {
        return 'completed';
    }

    if (rawStatus === 'rejected' || rawStatus === 'failed' || rawStatus === 'error') {
        return 'failed';
    }

    return 'pending';
}

function StatusIcon({ status, reducedMotion }) {
    const baseClass = reducedMotion ? '' : 'animate-pulse';

    if (status === 'completed') {
        return (
            <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100" aria-hidden="true">
                <svg className="h-10 w-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                </svg>
            </div>
        );
    }

    if (status === 'failed') {
        return (
            <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-100" aria-hidden="true">
                <svg className="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
        );
    }

    return (
        <div className={`mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 ${baseClass}`} aria-hidden="true">
            <svg className="h-10 w-10 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
    );
}

export default function PaymentPending({ payment, tenant, checkUrl, diagnostic = null }) {
    const [status, setStatus] = useState(normalizePaymentStatus(payment?.status));
    const [elapsedTime, setElapsedTime] = useState(0);
    const [pollingError, setPollingError] = useState(false);
    const [pollTick, setPollTick] = useState(0);
    const [reducedMotion, setReducedMotion] = useState(false);
    const [liveMessage, setLiveMessage] = useState('Estamos verificando tu pago.');

    const appDashboardHref = tenant?.slug ? route('filament.app.pages.dashboard', { tenant: tenant.slug }) : route('home');
    const appBillingHref = tenant?.slug ? route('filament.app.pages.manage-billing', { tenant: tenant.slug }) : route('home');

    const visibleDiagnostic = diagnostic || {
        surface: 'billing',
        status: status === 'pending' ? 'Pago en verificacion' : status === 'completed' ? 'Pago confirmado' : 'Pago no procesado',
        probable_cause: status === 'pending'
            ? 'Estamos esperando confirmacion del proveedor de pago o del webhook asociado.'
            : status === 'completed'
                ? 'El proveedor confirmo el cobro y el sistema actualizo el estado.'
                : 'No se recibio confirmacion del cobro o el estado llego como fallido.',
        recommended_action: status === 'pending'
            ? 'No cierres la pagina. Si tarda demasiado, abre billing oficial y comparte tu referencia con soporte.'
            : status === 'completed'
                ? 'Puedes volver al panel y continuar con la configuracion del negocio.'
                : 'Abre billing oficial para reintentar el flujo y comparte la referencia con soporte.',
        trace_id: payment?.reference_number || payment?.id || null,
    };

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return;

        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        const syncPreference = () => setReducedMotion(media.matches);

        syncPreference();
        media.addEventListener('change', syncPreference);

        return () => media.removeEventListener('change', syncPreference);
    }, []);

    useEffect(() => {
        if (status !== 'pending') return;

        const timer = setInterval(() => {
            setElapsedTime((prev) => prev + 1);
        }, 1000);

        return () => clearInterval(timer);
    }, [status]);

    useEffect(() => {
        if (status !== 'pending' || !checkUrl) return;

        const pollInterval = setInterval(async () => {
            try {
                const response = await fetch(checkUrl);
                const data = await response.json();
                const nextStatus = normalizePaymentStatus(data?.status);

                setPollingError(false);
                setPollTick((prev) => prev + 1);

                if (nextStatus !== 'pending') {
                    setStatus(nextStatus);
                    setLiveMessage(nextStatus === 'completed'
                        ? 'Pago confirmado. Te llevaremos al panel en unos segundos.'
                        : 'No se pudo confirmar el pago. Revisa las acciones disponibles.');

                    if (nextStatus === 'completed') {
                        setTimeout(() => {
                            router.visit(appDashboardHref);
                        }, 1500);
                    }
                } else {
                    setLiveMessage(`Seguimos verificando tu pago. Ultima revision hace ${Math.floor(elapsedTime / 60)} minuto(s).`);
                }
            } catch {
                setPollingError(true);
                setLiveMessage('No pudimos consultar el estado en este momento. Puedes reintentar sin salir de esta pagina.');
            }
        }, 5000);

        return () => clearInterval(pollInterval);
    }, [status, checkUrl, appDashboardHref, elapsedTime]);

    const formatElapsed = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    return (
        <PublicLayout>
            <Head>
                <title>Procesando Pago - Vito Business OS</title>
                <meta name="robots" content="noindex" />
            </Head>

            <div className="min-h-screen bg-slate-50 px-4 py-12">
                <div className="mx-auto w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                    <div className="sr-only" aria-live="polite" aria-atomic="true">{liveMessage}</div>
                    <StatusIcon status={status} reducedMotion={reducedMotion} />

                    <h1 className="mt-6 text-center text-2xl font-bold text-slate-950">
                        {status === 'pending' && 'Verificando tu pago'}
                        {status === 'completed' && 'Pago confirmado'}
                        {status === 'failed' && 'Pago no procesado'}
                    </h1>

                    <p className="mt-2 text-center text-sm text-slate-600" aria-live="polite">
                        {status === 'pending' && 'Tu pago esta en revision. Manten esta pagina abierta mientras validamos el estado.'}
                        {status === 'completed' && 'La validacion termino correctamente.'}
                        {status === 'failed' && 'No pudimos confirmar este pago. Puedes retomar desde billing oficial.'}
                    </p>

                    {status === 'pending' && !checkUrl && (
                        <div className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900" role="status" aria-live="polite">
                            Esta sesion no tiene endpoint de seguimiento en tiempo real. Usa billing oficial para confirmar avances.
                        </div>
                    )}

                    {payment && (
                        <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                            <div className="flex justify-between py-1"><span className="text-slate-500">Monto</span><span className="font-semibold text-slate-900">{currencyFormatter.format(payment.amount)}</span></div>
                            <div className="flex justify-between py-1"><span className="text-slate-500">Referencia</span><span className="font-mono text-slate-700">{payment.reference_number || `#${payment.id}`}</span></div>
                            <div className="flex justify-between py-1"><span className="text-slate-500">Estado</span><span className="font-medium text-slate-800">{status === 'completed' ? 'Aprobado' : status === 'failed' ? 'Rechazado' : 'Pendiente'}</span></div>
                        </div>
                    )}

                    {status === 'pending' && checkUrl && (
                        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status" aria-live="polite">
                            Tiempo transcurrido: {formatElapsed(elapsedTime)}. Revisiones completadas: {pollTick}.
                        </div>
                    )}

                    {pollingError && status === 'pending' && (
                        <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900" role="alert">
                            No logramos consultar el estado ahora. Reintenta en unos segundos o abre billing oficial para seguimiento.
                        </div>
                    )}

                    <SupportDiagnosticCard diagnostic={visibleDiagnostic} className="mt-6" />

                    <div className="mt-6 space-y-3">
                        {status === 'completed' && (
                            <Link href={appDashboardHref} className="block rounded-lg bg-emerald-600 px-4 py-3 text-center font-semibold text-white hover:bg-emerald-700">
                                Ir al panel
                            </Link>
                        )}

                        {(status === 'failed' || status === 'pending') && (
                            <Link href={appBillingHref} className="block rounded-lg bg-blue-600 px-4 py-3 text-center font-semibold text-white hover:bg-blue-700">
                                Abrir billing oficial
                            </Link>
                        )}

                        {status === 'pending' && (
                            <button
                                type="button"
                                onClick={() => router.reload({ only: ['payment', 'diagnostic'] })}
                                className="block w-full rounded-lg border border-slate-300 px-4 py-3 text-center font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Actualizar estado ahora
                            </button>
                        )}

                        {status === 'pending' && (
                            <a href="mailto:soporte@vitobusinessos.com?subject=Seguimiento%20de%20pago" className="block rounded-lg border border-slate-300 px-4 py-3 text-center font-medium text-slate-700 hover:bg-slate-50">
                                Contactar soporte
                            </a>
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
