/**
 * Pages/Tenant/Upgrade.jsx - Upgrade to PRO Page
 *
 * FEATURES:
 * - Plan comparison table
 * - Feature highlighting
 * - Payment method selection
 * - Animated pricing card
 *
 * PROPS:
 * @param {Object} props.tenant - Current tenant info
 * @param {Object} props.plans - { free: {...}, pro: {...} }
 * @param {Array} props.paymentMethods - Available payment methods
 */

import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';

// ═══════════════════════════════════════════════════════════════════════════
// FORMATTERS
// ═══════════════════════════════════════════════════════════════════════════
const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

// ═══════════════════════════════════════════════════════════════════════════
// FEATURE LIST
// ═══════════════════════════════════════════════════════════════════════════
const FEATURES = [
    { name: 'Productos', free: '10', pro: 'Ilimitados', highlight: true },
    { name: 'Imágenes por producto', free: '1', pro: '5' },
    { name: 'Categorías personalizadas', free: false, pro: true },
    { name: 'Horario de atención', free: true, pro: true },
    { name: 'Integración WhatsApp', free: true, pro: true },
    { name: 'Analytics avanzados', free: false, pro: true, highlight: true },
    { name: 'Cupones y promociones', free: false, pro: true },
    { name: 'Reportes PDF', free: false, pro: true },
    { name: 'QR personalizado', free: false, pro: true },
    { name: 'Prioridad en directorio', free: false, pro: true, highlight: true },
    { name: 'Soporte prioritario', free: false, pro: true },
    { name: 'Badge PRO verificado', free: false, pro: true },
];

// ═══════════════════════════════════════════════════════════════════════════
// FEATURE ROW
// ═══════════════════════════════════════════════════════════════════════════
function FeatureRow({ feature }) {
    const CheckIcon = () => (
        <svg className="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
        </svg>
    );

    const XIcon = () => (
        <svg className="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );

    return (
        <tr className={`border-b border-gray-100 ${feature.highlight ? 'bg-amber-50' : ''}`}>
            <td className="py-4 px-6 font-medium text-gray-900">
                {feature.name}
                {feature.highlight && (
                    <span className="ml-2 px-2 py-0.5 bg-amber-200 text-amber-800 text-xs font-bold rounded-full">
                        HOT
                    </span>
                )}
            </td>
            <td className="py-4 px-6 text-center">
                {typeof feature.free === 'boolean' ? (
                    feature.free ? <CheckIcon /> : <XIcon />
                ) : (
                    <span className="text-gray-600">{feature.free}</span>
                )}
            </td>
            <td className="py-4 px-6 text-center">
                {typeof feature.pro === 'boolean' ? (
                    feature.pro ? <CheckIcon /> : <XIcon />
                ) : (
                    <span className="font-bold text-blue-600">{feature.pro}</span>
                )}
            </td>
        </tr>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// PRICING CARD
// ═══════════════════════════════════════════════════════════════════════════
function PricingCard({ plan, isPopular, onSelect, selected }) {
    return (
        <div className={`relative rounded-3xl border-2 p-8 transition-all ${selected
            ? 'border-blue-600 bg-blue-50 shadow-xl scale-105'
            : isPopular
                ? 'border-blue-300 bg-white shadow-lg'
                : 'border-gray-200 bg-white'
            }`}>
            {/* Popular Badge */}
            {isPopular && (
                <div className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-full">
                    MÁS POPULAR
                </div>
            )}

            <div className="text-center mb-6">
                <h3 className="text-xl font-bold text-gray-900">{plan.name}</h3>
                <p className="text-gray-500 text-sm mt-1">{plan.description}</p>
            </div>

            <div className="text-center mb-6">
                <div className="flex items-baseline justify-center gap-1">
                    <span className="text-4xl font-extrabold text-gray-900">
                        {currencyFormatter.format(plan.price)}
                    </span>
                    <span className="text-gray-500">/mes</span>
                </div>
                {plan.original_price && plan.original_price > plan.price && (
                    <p className="text-sm text-gray-400 line-through mt-1">
                        {currencyFormatter.format(plan.original_price)}/mes
                    </p>
                )}
            </div>

            <button
                onClick={() => onSelect(plan.id)}
                className={`w-full py-4 rounded-xl font-bold transition-all ${selected
                    ? 'bg-blue-600 text-white'
                    : isPopular
                        ? 'bg-blue-600 hover:bg-blue-700 text-white'
                        : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                    }`}
            >
                {selected ? '✓ Seleccionado' : 'Seleccionar Plan'}
            </button>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// PAYMENT METHODS
// ═══════════════════════════════════════════════════════════════════════════
function PaymentMethodSelector({ methods, selected, onChange }) {
    const icons = {
        bank: '🏦',
        card: '💳',
        transfer: '📱',
    };

    return (
        <div className="space-y-3">
            <h3 className="font-bold text-gray-900 mb-4">Método de Pago</h3>
            {methods.map((method) => (
                <label
                    key={method.id}
                    className={`flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all ${selected === method.id
                        ? 'border-blue-600 bg-blue-50'
                        : 'border-gray-200 hover:border-gray-300'
                        }`}
                >
                    <input
                        type="radio"
                        name="payment_method"
                        value={method.id}
                        checked={selected === method.id}
                        onChange={() => onChange(method.id)}
                        className="w-5 h-5 text-blue-600"
                    />
                    <span className="text-2xl">{icons[method.type] || '💰'}</span>
                    <div className="flex-1">
                        <p className="font-semibold text-gray-900">{method.name}</p>
                        <p className="text-sm text-gray-500">{method.description}</p>
                    </div>
                </label>
            ))}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════
export default function Upgrade({ tenant, plans, paymentMethods = [] }) {
    const [selectedPlan, setSelectedPlan] = useState('pro');
    const [selectedPayment, setSelectedPayment] = useState(paymentMethods[0]?.id || null);

    const defaultPlans = plans || {
        free: {
            id: 'free',
            name: 'Gratis',
            description: 'Para empezar',
            price: 0,
        },
        pro: {
            id: 'pro',
            name: 'PRO',
            description: 'Para crecer',
            price: 499,
            original_price: 799,
        },
    };

    const defaultPaymentMethods = paymentMethods.length ? paymentMethods : [
        { id: 'bank', type: 'bank', name: 'Depósito Bancario', description: 'BAC, Ficohsa, Banpaís' },
        { id: 'transfer', type: 'transfer', name: 'Transferencia', description: 'Desde cualquier banco' },
    ];

    // ═══════════════════════════════════════════════════════════════════════════
    // FIX #8: Guard clause for plan selection
    // Principle: Only paid plans trigger backend payment flow
    // ═══════════════════════════════════════════════════════════════════════════
    const handleProceed = () => {
        // Guard clause: Free plan doesn't need payment
        if (selectedPlan === 'free') {
            // Already on free, just redirect to dashboard
            router.visit(route('tenant.dashboard'), {
                preserveState: false,
            });
            return;
        }

        // Paid plan - proceed to billing
        router.post(route('tenant.billing.upgrade'), {
            plan: selectedPlan,
            payment_method: selectedPayment,
        });
    };

    // Already PRO
    if (tenant?.is_pro) {
        return (
            <TenantLayout>
                <Head title="Plan PRO Activo" />
                <div className="min-h-screen bg-gray-50 flex items-center justify-center py-12">
                    <div className="text-center max-w-md">
                        <div className="w-24 h-24 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg className="w-12 h-12 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900 mb-2">¡Ya eres PRO!</h1>
                        <p className="text-gray-500 mb-6">
                            Tu cuenta ya tiene acceso a todas las funciones premium.
                        </p>
                        <div className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-100 text-emerald-700 rounded-full font-bold">
                            <span className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" />
                            Plan PRO Activo
                        </div>
                    </div>
                </div>
            </TenantLayout>
        );
    }

    return (
        <TenantLayout>
            <Head title="Upgrade a PRO" />

            <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white">
                {/* Header */}
                <header className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-16">
                    <div className="max-w-4xl mx-auto px-4 text-center">
                        <span className="inline-block px-4 py-2 bg-white/10 backdrop-blur rounded-full text-sm font-medium mb-6">
                            💎 Oferta por tiempo limitado
                        </span>
                        <h1 className="text-4xl font-extrabold mb-4">
                            Desbloquea todo el potencial de tu negocio
                        </h1>
                        <p className="text-xl text-blue-100 max-w-2xl mx-auto">
                            Productos ilimitados, analytics avanzados, y prioridad en el directorio
                        </p>
                    </div>
                </header>

                <main className="max-w-6xl mx-auto px-4 -mt-8">
                    {/* Pricing Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-3xl mx-auto mb-16">
                        <PricingCard
                            plan={defaultPlans.free}
                            selected={selectedPlan === 'free'}
                            onSelect={setSelectedPlan}
                        />
                        <PricingCard
                            plan={defaultPlans.pro}
                            isPopular
                            selected={selectedPlan === 'pro'}
                            onSelect={setSelectedPlan}
                        />
                    </div>

                    {/* Feature Comparison */}
                    <div className="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden mb-12">
                        <div className="p-6 bg-gray-50 border-b border-gray-100">
                            <h2 className="text-xl font-bold text-gray-900">Comparación de Planes</h2>
                        </div>
                        <table className="w-full">
                            <thead>
                                <tr className="bg-gray-50">
                                    <th className="py-4 px-6 text-left font-semibold text-gray-700">Característica</th>
                                    <th className="py-4 px-6 text-center font-semibold text-gray-700">Gratis</th>
                                    <th className="py-4 px-6 text-center font-semibold text-blue-600">PRO</th>
                                </tr>
                            </thead>
                            <tbody>
                                {FEATURES.map((feature, index) => (
                                    <FeatureRow key={index} feature={feature} />
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Payment Section */}
                    {selectedPlan === 'pro' && (
                        <div className="bg-white rounded-3xl shadow-xl border border-gray-100 p-8 max-w-2xl mx-auto mb-12">
                            <PaymentMethodSelector
                                methods={defaultPaymentMethods}
                                selected={selectedPayment}
                                onChange={setSelectedPayment}
                            />

                            <div className="mt-8 p-6 bg-gray-50 rounded-2xl">
                                <div className="flex justify-between items-center mb-4">
                                    <span className="text-gray-600">Plan PRO (mensual)</span>
                                    <span className="font-bold text-gray-900">
                                        {currencyFormatter.format(defaultPlans.pro.price)}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center text-lg font-bold">
                                    <span>Total a pagar</span>
                                    <span className="text-blue-600">
                                        {currencyFormatter.format(defaultPlans.pro.price)}
                                    </span>
                                </div>
                            </div>

                            <button
                                onClick={handleProceed}
                                disabled={!selectedPayment}
                                className="w-full mt-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl transition-all disabled:opacity-50 shadow-lg shadow-blue-500/25"
                            >
                                🚀 Continuar al Pago
                            </button>

                            <p className="text-center text-sm text-gray-400 mt-4">
                                Pago seguro. Cancela cuando quieras.
                            </p>
                        </div>
                    )}
                </main>
            </div>
        </TenantLayout>
    );
}
