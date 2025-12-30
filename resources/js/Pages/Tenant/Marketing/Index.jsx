/**
 * Tenant/Marketing/Index.jsx - Promotion Engine
 *
 * FEATURES:
 * - Card-based coupon grid (not tables)
 * - Modal creation form (maintain context)
 * - Optimistic toggle via CouponCard component
 * - Clipboard copy with visual feedback
 *
 * PROPS (from MarketingController@index):
 * @param {Object} props.coupons - Array of coupon objects
 * @param {Object} props.stats - { total_coupons, active_coupons, total_redemptions }
 * @param {Object} props.tenant - { id, name, slug, is_pro }
 */

import { useState, useCallback } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import CouponCard from '@/Components/Marketing/CouponCard';
import StatCard from '@/Components/Stats/StatCard';

// ═══════════════════════════════════════════════════════════════════════════
// CREATE COUPON MODAL
// ═══════════════════════════════════════════════════════════════════════════
function CreateCouponModal({ isOpen, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        type: 'percentage',
        value: '',
        usage_limit: '',
        expires_at: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('tenant.coupons.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-gray-500/75 transition-opacity" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h3 className="text-xl font-bold text-gray-900">🏷️ Nuevo Cupón</h3>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Code */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Código del Cupón *
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                placeholder="VERANO2025"
                                className={`w-full px-4 py-3 bg-gray-50 border rounded-xl font-mono uppercase tracking-wide focus:bg-white focus:ring-2 focus:ring-blue-500 ${errors.code ? 'border-red-300' : 'border-gray-200'
                                    }`}
                            />
                            {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                        </div>

                        {/* Type & Value */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Descuento
                                </label>
                                <select
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="percentage">Porcentaje (%)</option>
                                    <option value="fixed">Monto Fijo (L.)</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Valor *
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max={data.type === 'percentage' ? 100 : 99999}
                                    value={data.value}
                                    onChange={(e) => setData('value', e.target.value)}
                                    placeholder={data.type === 'percentage' ? '20' : '100'}
                                    className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 ${errors.value ? 'border-red-300' : 'border-gray-200'
                                        }`}
                                />
                                {errors.value && <p className="mt-1 text-sm text-red-500">{errors.value}</p>}
                            </div>
                        </div>

                        {/* Usage Limit */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Límite de Uso (opcional)
                            </label>
                            <input
                                type="number"
                                min="1"
                                value={data.usage_limit}
                                onChange={(e) => setData('usage_limit', e.target.value)}
                                placeholder="Sin límite"
                                className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                            />
                            <p className="mt-1 text-xs text-gray-500">Dejá vacío para usos ilimitados</p>
                        </div>

                        {/* Expiration */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Fecha de Expiración (opcional)
                            </label>
                            <input
                                type="date"
                                value={data.expires_at}
                                onChange={(e) => setData('expires_at', e.target.value)}
                                min={new Date().toISOString().split('T')[0]}
                                className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        {/* Actions */}
                        <div className="flex gap-3 pt-4">
                            <button
                                type="button"
                                onClick={onClose}
                                className="flex-1 px-4 py-3 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 font-medium"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium disabled:opacity-50"
                            >
                                {processing ? 'Creando...' : 'Crear Cupón'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function MarketingIndex({ coupons = [], stats = {}, tenant }) {
    const [showCreateModal, setShowCreateModal] = useState(false);

    const handleToggle = useCallback((couponId, newState) => {
        // Optional: Update local state if needed for immediate reflection
    }, []);

    return (
        <TenantLayout>
            <Head title="Marketing" />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <h1 className="text-xl font-bold text-gray-900">🎯 Marketing</h1>
                            <button
                                onClick={() => setShowCreateModal(true)}
                                className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-blue-500/25"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Nuevo Cupón
                            </button>
                        </div>
                    </div>
                </header>

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Stats Row */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <StatCard
                            title="Total Cupones"
                            value={stats.total_coupons || 0}
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Cupones Activos"
                            value={stats.active_coupons || 0}
                            trend={stats.active_trend}
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Total Canjeados"
                            value={stats.total_redemptions || 0}
                            trend={stats.redemption_trend}
                            trendLabel="vs mes anterior"
                            icon={
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            }
                        />
                    </div>

                    {/* Coupons Grid */}
                    {coupons.length > 0 ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            {coupons.map((coupon) => (
                                <CouponCard
                                    key={coupon.id}
                                    coupon={coupon}
                                    onToggle={handleToggle}
                                />
                            ))}
                        </div>
                    ) : (
                        /* Empty State */
                        <div className="text-center py-16 bg-white rounded-2xl border border-gray-100">
                            <div className="inline-flex items-center justify-center w-20 h-20 bg-indigo-100 rounded-full mb-6">
                                <svg className="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-2">Sin cupones activos</h3>
                            <p className="text-gray-500 mb-6 max-w-sm mx-auto">
                                Crea tu primer cupón de descuento para atraer más clientes y aumentar tus ventas.
                            </p>
                            <button
                                onClick={() => setShowCreateModal(true)}
                                className="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition-all shadow-lg"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Crear mi primer cupón
                            </button>
                        </div>
                    )}
                </main>

                {/* Create Modal */}
                <CreateCouponModal
                    isOpen={showCreateModal}
                    onClose={() => setShowCreateModal(false)}
                />
            </div>
        </TenantLayout>
    );
}
