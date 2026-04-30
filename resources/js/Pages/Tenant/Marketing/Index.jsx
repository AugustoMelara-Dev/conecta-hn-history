import { useState } from 'react';
import { Head } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import CouponCard from '@/Components/Marketing/CouponCard';
import StatCard from '@/Components/Stats/StatCard';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

function MarketingReadOnlyModal({ isOpen, onClose }) {
    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Marketing en modo lectura</DialogTitle>
                    <DialogDescription>
                        Este workspace legacy queda disponible solo para consulta en el launch actual.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <p className="text-sm leading-6 text-muted-foreground">
                        La gestión operativa de campañas y automatizaciones vive en el panel principal.
                    </p>
                    <Alert variant="warning">
                        <AlertDescription>
                            Dejamos esta vista sin escritura para evitar endpoints rotos o flujos a medio implementar.
                        </AlertDescription>
                    </Alert>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Entendido
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function MarketingIndex({ coupons = [], stats = {}, canManageCoupons = false }) {
    const [showModal, setShowModal] = useState(false);

    return (
        <TenantLayout>
            <Head title="Marketing" />

            <div className="min-h-screen bg-gray-50">
                <header className="sticky top-0 z-40 border-b border-gray-200 bg-white">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">Marketing</h1>
                            {!canManageCoupons && (
                                <p className="text-sm text-gray-500">Vista de consulta del workspace legacy.</p>
                            )}
                        </div>
                        <button
                            onClick={() => setShowModal(true)}
                            className={`inline-flex items-center gap-2 rounded-xl px-5 py-2.5 font-semibold transition-all ${
                                canManageCoupons
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/25 hover:bg-blue-700'
                                    : 'bg-gray-200 text-gray-700'
                            }`}
                        >
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            {canManageCoupons ? 'Nuevo cupon' : 'Gestion no disponible'}
                        </button>
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    {!canManageCoupons && (
                        <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                            Esta superficie queda fuera del scope principal de lanzamiento. Se mantiene visible para consulta, sin acciones de escritura.
                        </div>
                    )}

                    <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <StatCard
                            title="Total cupones"
                            value={stats.total_coupons || 0}
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Cupones activos"
                            value={stats.active_coupons || 0}
                            trend={stats.active_trend}
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                        <StatCard
                            title="Total canjeados"
                            value={stats.total_redemptions || 0}
                            trend={stats.redemption_trend}
                            trendLabel="vs mes anterior"
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            }
                        />
                    </div>

                    {coupons.length > 0 ? (
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {coupons.map((coupon) => (
                                <CouponCard key={coupon.id} coupon={coupon} canManageCoupons={canManageCoupons} />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-gray-100 bg-white py-16 text-center">
                            <div className="mb-6 inline-flex h-20 w-20 items-center justify-center rounded-full bg-indigo-100">
                                <svg className="h-10 w-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                            <h3 className="mb-2 text-xl font-bold text-gray-900">Sin cupones activos</h3>
                            <p className="mx-auto mb-6 max-w-sm text-gray-500">
                                Revisa tu configuracion de marketing o mueve este flujo al panel principal antes de abrir escritura en esta superficie.
                            </p>
                            <button
                                onClick={() => setShowModal(true)}
                                className={`inline-flex items-center gap-2 rounded-xl px-6 py-3 font-semibold transition-all ${
                                    canManageCoupons ? 'bg-indigo-600 text-white shadow-lg hover:bg-indigo-700' : 'bg-gray-200 text-gray-700'
                                }`}
                            >
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                {canManageCoupons ? 'Crear mi primer cupon' : 'Gestion de cupones pendiente'}
                            </button>
                        </div>
                    )}
                </main>

                <MarketingReadOnlyModal isOpen={showModal} onClose={() => setShowModal(false)} />
            </div>
        </TenantLayout>
    );
}
