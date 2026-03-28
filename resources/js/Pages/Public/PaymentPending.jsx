/**
 * Pages/Public/PaymentPending.jsx - Payment Processing Status
 *
 * FEATURES:
 * - Real-time status polling
 * - Animated progress states
 * - Timeout handling
 * - Support contact fallback
 *
 * PROPS:
 * @param {Object} props.payment - { id, status, amount, method, created_at }
 * @param {Object} props.tenant - Tenant info
 * @param {string} props.checkUrl - URL to poll for status updates
 */

import { useState, useEffect, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

// ═══════════════════════════════════════════════════════════════════════════
// FORMATTERS
// ═══════════════════════════════════════════════════════════════════════════
const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('es-HN', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

// ═══════════════════════════════════════════════════════════════════════════
// STATUS COMPONENTS
// ═══════════════════════════════════════════════════════════════════════════
function PendingAnimation() {
    return (
        <div className="relative">
            <div className="w-32 h-32 rounded-full bg-amber-100 flex items-center justify-center mx-auto">
                <div className="w-24 h-24 rounded-full bg-amber-200 flex items-center justify-center animate-pulse">
                    <svg className="w-12 h-12 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            {/* Orbiting dots */}
            <div className="absolute inset-0 animate-spin" style={{ animationDuration: '3s' }}>
                <div className="absolute top-0 left-1/2 w-3 h-3 -ml-1.5 bg-amber-500 rounded-full" />
            </div>
            <div className="absolute inset-0 animate-spin" style={{ animationDuration: '3s', animationDelay: '1s' }}>
                <div className="absolute bottom-0 left-1/2 w-3 h-3 -ml-1.5 bg-amber-400 rounded-full" />
            </div>
        </div>
    );
}

function SuccessAnimation() {
    return (
        <div className="w-32 h-32 rounded-full bg-emerald-100 flex items-center justify-center mx-auto animate-bounce-in">
            <div className="w-24 h-24 rounded-full bg-emerald-500 flex items-center justify-center">
                <svg className="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                </svg>
            </div>
        </div>
    );
}

function FailedAnimation() {
    return (
        <div className="w-32 h-32 rounded-full bg-red-100 flex items-center justify-center mx-auto">
            <div className="w-24 h-24 rounded-full bg-red-500 flex items-center justify-center">
                <svg className="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// TIMELINE
// ═══════════════════════════════════════════════════════════════════════════
function PaymentTimeline({ status }) {
    const steps = [
        { id: 'created', label: 'Orden Creada', description: 'Tu orden ha sido registrada' },
        { id: 'processing', label: 'Procesando Pago', description: 'Verificando transacción' },
        { id: 'confirmed', label: 'Pago Confirmado', description: 'Transacción exitosa' },
    ];

    const currentIndex = status === 'pending' ? 1 : status === 'completed' ? 2 : 0;

    return (
        <div className="space-y-4 mt-8">
            {steps.map((step, index) => (
                <div key={step.id} className="flex items-start gap-4">
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${index < currentIndex
                            ? 'bg-emerald-500 text-white'
                            : index === currentIndex
                                ? 'bg-amber-500 text-white animate-pulse'
                                : 'bg-gray-200 text-gray-400'
                        }`}>
                        {index < currentIndex ? (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        ) : (
                            <span className="font-bold">{index + 1}</span>
                        )}
                    </div>
                    <div className="flex-1 pt-1">
                        <p className={`font-semibold ${index <= currentIndex ? 'text-gray-900' : 'text-gray-400'}`}>
                            {step.label}
                        </p>
                        <p className="text-sm text-gray-500">{step.description}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════
export default function PaymentPending({ payment, tenant, checkUrl }) {
    const [status, setStatus] = useState(payment?.status || 'pending');
    const [elapsedTime, setElapsedTime] = useState(0);
    const appDashboardHref = tenant?.slug ? route('filament.app.pages.dashboard', { tenant: tenant.slug }) : route('dashboard');
    const appBillingHref = tenant?.slug ? route('filament.app.pages.manage-billing', { tenant: tenant.slug }) : route('dashboard');

    // Poll for status updates
    useEffect(() => {
        if (status !== 'pending') return;

        const pollInterval = setInterval(() => {
            fetch(checkUrl || `/api/payments/${payment?.id}/status`)
                .then(res => res.json())
                .then(data => {
                    if (data.status && data.status !== 'pending') {
                        setStatus(data.status);
                        if (data.status === 'completed') {
                            // Redirect to success page after delay
                            setTimeout(() => {
                                router.visit(appDashboardHref);
                            }, 2000);
                        }
                    }
                })
                .catch(() => { });
        }, 5000); // Poll every 5 seconds

        return () => clearInterval(pollInterval);
    }, [status, checkUrl, payment?.id]);

    // Elapsed time counter
    useEffect(() => {
        const timer = setInterval(() => {
            setElapsedTime(prev => prev + 1);
        }, 1000);
        return () => clearInterval(timer);
    }, []);

    const formatElapsed = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    return (
        <PublicLayout>
            <Head>
                <title>Procesando Pago - ConectaHN</title>
                <meta name="robots" content="noindex" />
            </Head>

            <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white flex items-center justify-center py-12 px-4">
                <div className="max-w-lg w-full">
                    <div className="bg-white rounded-3xl shadow-xl border border-gray-100 p-8 sm:p-12 text-center">
                        {/* Status Icon */}
                        {status === 'pending' && <PendingAnimation />}
                        {status === 'completed' && <SuccessAnimation />}
                        {status === 'failed' && <FailedAnimation />}

                        {/* Title */}
                        <h1 className="text-2xl font-bold text-gray-900 mt-8 mb-2">
                            {status === 'pending' && 'Verificando tu Pago'}
                            {status === 'completed' && '¡Pago Confirmado!'}
                            {status === 'failed' && 'Pago No Procesado'}
                        </h1>

                        <p className="text-gray-500 mb-6">
                            {status === 'pending' && 'Esto puede tomar unos minutos. No cierres esta página.'}
                            {status === 'completed' && 'Tu suscripción ha sido activada exitosamente.'}
                            {status === 'failed' && 'Hubo un problema procesando tu pago. Por favor intenta de nuevo.'}
                        </p>

                        {/* Payment Summary */}
                        {payment && (
                            <div className="bg-gray-50 rounded-2xl p-6 mb-6 text-left">
                                <div className="flex justify-between items-center mb-3">
                                    <span className="text-gray-500">Monto</span>
                                    <span className="text-xl font-bold text-gray-900">
                                        {currencyFormatter.format(payment.amount)}
                                    </span>
                                </div>
                                {payment.method && (
                                    <div className="flex justify-between items-center mb-3">
                                        <span className="text-gray-500">Método</span>
                                        <span className="font-medium text-gray-700">{payment.method}</span>
                                    </div>
                                )}
                                <div className="flex justify-between items-center">
                                    <span className="text-gray-500">Referencia</span>
                                    <span className="font-mono text-sm text-gray-600">#{payment.id}</span>
                                </div>
                            </div>
                        )}

                        {/* Elapsed Time */}
                        {status === 'pending' && (
                            <div className="text-sm text-gray-400 mb-6">
                                Tiempo transcurrido: {formatElapsed(elapsedTime)}
                            </div>
                        )}

                        {/* Timeline */}
                        {status === 'pending' && (
                            <PaymentTimeline status={status} />
                        )}

                        {/* Actions */}
                        <div className="mt-8 space-y-3">
                            {status === 'completed' && (
                                <Link
                                    href={appDashboardHref}
                                    className="block w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-colors"
                                >
                                    Ir a mi Panel
                                </Link>
                            )}

                            {status === 'failed' && (
                                <>
                                    <Link
                                        href={appBillingHref}
                                        className="block w-full py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors"
                                    >
                                        Intentar de Nuevo
                                    </Link>
                                    <a
                                        href="mailto:soporte@conectahn.com?subject=Problema con pago"
                                        className="block w-full py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-colors"
                                    >
                                        Contactar Soporte
                                    </a>
                                </>
                            )}

                            {status === 'pending' && (
                                <p className="text-xs text-gray-400">
                                    ¿Problemas?{' '}
                                    <a href="mailto:soporte@conectahn.com" className="text-blue-600 hover:underline">
                                        Contacta a soporte
                                    </a>
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Custom Animation Styles */}
            <style>{`
                @keyframes bounce-in {
                    0% { transform: scale(0); }
                    50% { transform: scale(1.1); }
                    100% { transform: scale(1); }
                }
                .animate-bounce-in { animation: bounce-in 0.5s ease-out; }
            `}</style>
        </PublicLayout>
    );
}
