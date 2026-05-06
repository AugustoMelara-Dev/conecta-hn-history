/**
 * Tenant/Billing/Index.jsx - Subscription Management
 *
 * FEATURES:
 * - FREE: Aggressive upsell landing
 * - PRO: Subscription status + invoice ledger
 * - Client-side date/currency formatting via Intl APIs
 *
 * PROPS (from BillingController@index):
 * @param {Object} props.tenant - { id, name, slug, is_pro }
 * @param {Object|null} props.subscription - { status, renewal_date, plan }
 * @param {Array} props.payments - Payment history
 * @param {Object} props.bankOptions - { bac: 'BAC Credomatic', ... }
 */

import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';

// ═══════════════════════════════════════════════════════════════════════════
// FORMATTERS (Client-side via Intl APIs - as required)
// ═══════════════════════════════════════════════════════════════════════════
const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat('es-HN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
});

// ═══════════════════════════════════════════════════════════════════════════
// STATUS BADGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
function StatusBadge({ status }) {
    const config = {
        approved: { bg: 'bg-green-100', text: 'text-green-800', label: 'APROBADO' },
        rejected: { bg: 'bg-red-100', text: 'text-red-800', label: 'RECHAZADO' },
        pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'PENDIENTE' },
    };

    const { bg, text, label } = config[status] || config.pending;

    return (
        <span className={`px-2 py-1 text-xs font-bold rounded-full ${bg} ${text}`}>
            {label}
        </span>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// SUBSCRIPTION STATUS CARD
// ═══════════════════════════════════════════════════════════════════════════
function SubscriptionStatusCard({ subscription, hasPendingPayment, onReportPayment }) {
    const statusColors = {
        active: { bg: 'bg-green-100', icon: 'text-green-600' },
        past_due: { bg: 'bg-red-100', icon: 'text-red-600' },
        grace: { bg: 'bg-yellow-100', icon: 'text-yellow-600' },
        default: { bg: 'bg-gray-100', icon: 'text-gray-400' },
    };

    const color = statusColors[subscription?.status] || statusColors.default;

    const statusMessages = {
        active: 'Tu suscripción está activa.',
        past_due: 'Tu pago está vencido. Renueva para mantener el acceso PRO.',
        grace: 'Período de gracia. Renueva antes de perder acceso.',
    };

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
            <div className="flex flex-col md:flex-row items-center justify-between gap-6">
                <div className="flex items-center gap-4">
                    <div className={`w-16 h-16 rounded-full flex items-center justify-center ${color.bg}`}>
                        {subscription?.status === 'active' ? (
                            <svg className={`w-8 h-8 ${color.icon}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        ) : subscription?.status === 'past_due' ? (
                            <svg className={`w-8 h-8 ${color.icon}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        ) : (
                            <svg className={`w-8 h-8 ${color.icon}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        )}
                    </div>
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">Estado de la Suscripción</h2>
                        <p className="text-gray-600">{statusMessages[subscription?.status] || 'Estado desconocido'}</p>
                    </div>
                </div>

                <button
                    onClick={onReportPayment}
                    className="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-md transition-colors flex items-center gap-2"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Reportar Pago
                </button>
            </div>

            {hasPendingPayment && (
                <div className="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-100">
                    <p className="text-sm text-blue-800">
                        📋 <strong>Pago en revisión:</strong> Tienes un pago pendiente de verificación. Te notificaremos cuando sea aprobado.
                    </p>
                </div>
            )}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// INVOICE LEDGER TABLE
// ═══════════════════════════════════════════════════════════════════════════
function InvoiceLedger({ payments, bankOptions }) {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100">
                <h3 className="font-bold text-gray-900">Historial de Pagos</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Banco</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {payments.length > 0 ? (
                            payments.map((payment) => (
                                <tr key={payment.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {dateFormatter.format(new Date(payment.created_at))}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                        {payment.reference_number}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {bankOptions?.[payment.bank] || payment.bank}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        {currencyFormatter.format(payment.amount)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <StatusBadge status={payment.status} />
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                                    No hay pagos registrados aún.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// UPSELL LANDING (FREE USERS)
// ═══════════════════════════════════════════════════════════════════════════
function UpsellLanding({ onUpgrade }) {
    const freeFeatures = [
        { included: true, text: 'Catálogo Básico (10 productos)' },
        { included: true, text: 'Link de Tienda' },
        { included: false, text: 'Pedidos por WhatsApp' },
        { included: false, text: 'Gestión de Pedidos (Kanban)' },
        { included: false, text: 'Reportes Avanzados' },
    ];

    const proFeatures = [
        'Productos Ilimitados',
        'Carrito + WhatsApp Checkout',
        'Tablero de Pedidos (Kanban)',
        'Reportes y Analytics',
        'Soporte Prioritario',
    ];

    return (
        <div className="bg-white rounded-2xl shadow-xl overflow-hidden border border-indigo-100">
            <div className="bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-12 text-center text-white">
                <h2 className="text-3xl font-extrabold mb-4">Lleva tu Negocio al Siguiente Nivel</h2>
                <p className="text-indigo-100 text-lg max-w-2xl mx-auto">
                    Desbloquea el poder del E-commerce real. Automatiza tus pedidos y profesionaliza tu imagen.
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-0">
                {/* FREE Column */}
                <div className="p-8 border-b md:border-b-0 md:border-r border-gray-100">
                    <h3 className="text-lg font-bold text-gray-500 mb-6">Plan Actual (Gratis)</h3>
                    <ul className="space-y-4">
                        {freeFeatures.map((feature, idx) => (
                            <li key={idx} className={`flex items-center ${feature.included ? 'text-gray-600' : 'text-gray-400'}`}>
                                <svg className={`w-5 h-5 mr-3 flex-shrink-0 ${feature.included ? 'text-green-500' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d={feature.included ? "M5 13l4 4L19 7" : "M6 18L18 6M6 6l12 12"} />
                                </svg>
                                {feature.text}
                            </li>
                        ))}
                    </ul>
                </div>

                {/* PRO Column */}
                <div className="p-8 bg-gradient-to-br from-gray-50 to-indigo-50">
                    <div className="flex justify-between items-center mb-6">
                        <h3 className="text-2xl font-bold text-gray-900">Plan PRO</h3>
                        <span className="px-3 py-1 bg-indigo-100 text-indigo-700 font-bold rounded-full text-sm">RECOMENDADO</span>
                    </div>
                    <div className="mb-8">
                        <span className="text-4xl font-extrabold text-gray-900">L. 1,500</span>
                        <span className="text-gray-500"> / mes</span>
                    </div>

                    <ul className="space-y-4 mb-8">
                        {proFeatures.map((feature, idx) => (
                            <li key={idx} className="flex items-center text-gray-800 font-medium">
                                <svg className="w-5 h-5 text-indigo-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                </svg>
                                {feature}
                            </li>
                        ))}
                    </ul>

                    <button
                        onClick={onUpgrade}
                        className="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5"
                    >
                        🚀 Contratar Plan PRO
                    </button>
                </div>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function BillingIndex({ tenant, subscription, payments = [], bankOptions = {} }) {
    const [showPaymentModal, setShowPaymentModal] = useState(false);

    const hasPendingPayment = payments.some(p => p.status === 'pending');

    const handleUpgrade = () => {
        // Redirect to Stripe/Payment Portal (external URL, not Inertia)
        window.location.href = `/upgrade/${tenant?.slug}`;
    };

    return (
        <TenantLayout>
            <Head title="Facturación" />

            <div className="min-h-screen bg-gray-50 pb-12">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <h1 className="text-xl font-bold text-gray-900">💳 Facturación</h1>
                            {tenant?.is_pro ? (
                                <span className="px-3 py-1 bg-emerald-100 text-emerald-700 text-sm font-bold rounded-full">PLAN PRO</span>
                            ) : (
                                <span className="px-3 py-1 bg-gray-100 text-gray-600 text-sm font-bold rounded-full">PLAN GRATUITO</span>
                            )}
                        </div>
                    </div>
                </header>

                <main className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {tenant?.is_pro ? (
                        <>
                            <SubscriptionStatusCard
                                subscription={subscription}
                                hasPendingPayment={hasPendingPayment}
                                onReportPayment={() => setShowPaymentModal(true)}
                            />
                            <InvoiceLedger payments={payments} bankOptions={bankOptions} />
                        </>
                    ) : (
                        <UpsellLanding onUpgrade={handleUpgrade} />
                    )}
                </main>
            </div>
        </TenantLayout>
    );
}
