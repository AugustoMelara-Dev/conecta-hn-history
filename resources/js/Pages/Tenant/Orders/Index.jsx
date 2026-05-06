/**
 * Tenant/Orders/Index.jsx - Order Management Dashboard
 *
 * PURPOSE: Main order management page with Kanban board (PRO) or Table (FREE).
 * Implements drag-and-drop workflow using @dnd-kit/core.
 * Uses OPTIMISTIC UI: Updates state immediately, syncs to server in background.
 *
 * PROPS (from Controller - OrderController@index):
 * - orders: { pending: [], processing: [], completed: [], cancelled: [] } // Grouped
 * - tenant: { id, name, slug, is_pro }
 * - products: [] // For POS modal
 */

import { useState, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    useSensor,
    useSensors,
    closestCenter,
} from '@dnd-kit/core';
import {
    SortableContext,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import TenantLayout from '@/Layouts/TenantLayout';
import KanbanCard from '@/Components/Orders/KanbanCard';

// ═══════════════════════════════════════════════════════════════════════════
// CONSTANTS: Column Configuration
// ═══════════════════════════════════════════════════════════════════════════
const COLUMNS = [
    { id: 'pending', label: '⏳ Pendientes', color: 'amber', nextStatus: 'processing' },
    { id: 'processing', label: '🔥 Preparando', color: 'blue', nextStatus: 'completed' },
    { id: 'completed', label: '✅ Completados', color: 'emerald', nextStatus: null },
    { id: 'cancelled', label: '❌ Cancelados', color: 'red', nextStatus: null },
];

const STATUS_LABELS = {
    pending: 'Pendiente',
    processing: 'Preparando',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

// ═══════════════════════════════════════════════════════════════════════════
// CURRENCY FORMATTER
// ═══════════════════════════════════════════════════════════════════════════
const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 2,
});

// ═══════════════════════════════════════════════════════════════════════════
// KANBAN COLUMN COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
function KanbanColumn({ id, label, color, orders, onAdvance, nextStatus }) {
    const colorClasses = {
        amber: { bg: 'bg-amber-50', badge: 'bg-amber-200 text-amber-800', empty: 'text-amber-600' },
        blue: { bg: 'bg-blue-50', badge: 'bg-blue-200 text-blue-800', empty: 'text-blue-600' },
        emerald: { bg: 'bg-emerald-50', badge: 'bg-emerald-200 text-emerald-800', empty: 'text-emerald-600' },
        red: { bg: 'bg-red-50', badge: 'bg-red-200 text-red-800', empty: 'text-red-600' },
    };

    const colors = colorClasses[color] || colorClasses.amber;

    return (
        <div className="w-80 flex-shrink-0">
            <div className={`${colors.bg} rounded-xl p-4`}>
                {/* Column Header */}
                <div className="flex items-center justify-between mb-3">
                    <h3 className={`font-semibold text-${color}-800`}>{label}</h3>
                    <span className={`px-2 py-0.5 ${colors.badge} text-xs font-medium rounded-full`}>
                        {orders.length}
                    </span>
                </div>

                {/* Cards Container */}
                <SortableContext items={orders.map(o => o.id)} strategy={verticalListSortingStrategy}>
                    <div className="space-y-3 max-h-[60vh] overflow-y-auto">
                        {orders.length > 0 ? (
                            orders.map((order) => (
                                <KanbanCard
                                    key={order.id}
                                    order={order}
                                    onAdvance={onAdvance}
                                    showAdvanceButton={!!nextStatus}
                                />
                            ))
                        ) : (
                            <p className={`text-sm ${colors.empty} text-center py-4`}>
                                Sin pedidos
                            </p>
                        )}
                    </div>
                </SortableContext>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// FREE USER TABLE VIEW
// ═══════════════════════════════════════════════════════════════════════════
function OrdersTable({ orders, onAdvance }) {
    // Flatten all orders into a single array for table view
    const allOrders = Object.values(orders).flat();

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {allOrders.map((order) => (
                        <tr key={order.id}>
                            <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                {order.order_number || `#${order.id}`}
                            </td>
                            <td className="px-6 py-4 text-sm text-gray-500">
                                {new Date(order.created_at).toLocaleTimeString('es-HN', { hour: '2-digit', minute: '2-digit' })}
                            </td>
                            <td className="px-6 py-4 text-sm text-gray-900">
                                {order.customer_name || 'Sin nombre'}
                            </td>
                            <td className="px-6 py-4">
                                <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(order.status)}`}>
                                    {STATUS_LABELS[order.status] || order.status}
                                </span>
                            </td>
                            <td className="px-6 py-4 text-sm font-bold text-gray-900">
                                {currencyFormatter.format(order.total)}
                            </td>
                            <td className="px-6 py-4 text-sm">
                                {['pending', 'processing'].includes(order.status) ? (
                                    <button
                                        onClick={() => onAdvance(order.id)}
                                        className="text-blue-600 hover:text-blue-800 font-medium"
                                    >
                                        Avanzar →
                                    </button>
                                ) : (
                                    <span className="text-gray-400">-</span>
                                )}
                            </td>
                        </tr>
                    ))}
                    {allOrders.length === 0 && (
                        <tr>
                            <td colSpan={6} className="px-6 py-12 text-center text-gray-500">
                                <p>No hay pedidos hoy</p>
                                <p className="text-sm mt-1">Los pedidos aparecerán aquí</p>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function getStatusColor(status) {
    const colors = {
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function OrdersIndex({ orders: initialOrders, tenant, products }) {
    // ─────────────────────────────────────────────────────────────────────────
    // STATE: Client-side order management (Optimistic UI)
    // ─────────────────────────────────────────────────────────────────────────
    const [orders, setOrders] = useState(initialOrders);
    const [activeOrder, setActiveOrder] = useState(null);
    const [isCreating, setIsCreating] = useState(false);

    // DnD Sensors
    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 8 },
        })
    );

    // ─────────────────────────────────────────────────────────────────────────
    // HANDLERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Advance order to next status (Optimistic UI)
     * 1. Update local state IMMEDIATELY
     * 2. Sync to server in BACKGROUND
     */
    const handleAdvanceOrder = useCallback((orderId) => {
        // Find the order and its current column
        let orderToMove = null;
        let sourceColumn = null;

        for (const [columnId, columnOrders] of Object.entries(orders)) {
            const found = columnOrders.find(o => o.id === orderId);
            if (found) {
                orderToMove = found;
                sourceColumn = columnId;
                break;
            }
        }

        if (!orderToMove) return;

        // Determine next status
        const columnConfig = COLUMNS.find(c => c.id === sourceColumn);
        const nextStatus = columnConfig?.nextStatus;
        if (!nextStatus) return;

        // ═══════════════════════════════════════════════════════════════════
        // OPTIMISTIC UPDATE: Move order immediately in UI
        // ═══════════════════════════════════════════════════════════════════
        const previousOrders = { ...orders };
        setOrders(prev => ({
            ...prev,
            [sourceColumn]: prev[sourceColumn].filter(o => o.id !== orderId),
            [nextStatus]: [...prev[nextStatus], { ...orderToMove, status: nextStatus }],
        }));

        // ═══════════════════════════════════════════════════════════════════
        // BACKGROUND SYNC: Persist to server (no await)
        // ═══════════════════════════════════════════════════════════════════
        router.put(
            route('tenant.orders.update-status', orderId),
            { status: nextStatus },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    // ROLLBACK on server error
                    setOrders(previousOrders);
                },
            }
        );
    }, [orders]);

    /**
     * Handle drag end event
     */
    const handleDragEnd = useCallback((event) => {
        const { active, over } = event;
        setActiveOrder(null);

        if (!over) return;

        // Determine target column from drop zone
        const targetColumn = over.id;
        if (!COLUMNS.find(c => c.id === targetColumn)) return;

        // Find source order
        let orderToMove = null;
        let sourceColumn = null;

        for (const [columnId, columnOrders] of Object.entries(orders)) {
            const found = columnOrders.find(o => o.id === active.id);
            if (found) {
                orderToMove = found;
                sourceColumn = columnId;
                break;
            }
        }

        if (!orderToMove || sourceColumn === targetColumn) return;

        // OPTIMISTIC UPDATE
        const previousOrders = { ...orders };
        setOrders(prev => ({
            ...prev,
            [sourceColumn]: prev[sourceColumn].filter(o => o.id !== active.id),
            [targetColumn]: [...prev[targetColumn], { ...orderToMove, status: targetColumn }],
        }));

        // SERVER SYNC
        router.put(
            route('tenant.orders.update-status', active.id),
            { status: targetColumn },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setOrders(previousOrders),
            }
        );
    }, [orders]);

    const handleDragStart = useCallback((event) => {
        const orderId = event.active.id;
        for (const columnOrders of Object.values(orders)) {
            const found = columnOrders.find(o => o.id === orderId);
            if (found) {
                setActiveOrder(found);
                break;
            }
        }
    }, [orders]);

    // ─────────────────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────────────────
    const totalOrders = Object.values(orders).flat().length;

    return (
        <TenantLayout>
            <Head title="Gestión de Pedidos" />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <div className="flex items-center gap-3">
                                <h1 className="text-xl font-bold text-gray-900">Gestión de Pedidos</h1>
                                <span className="px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">
                                    Hoy: {totalOrders}
                                </span>
                            </div>
                            <button
                                onClick={() => setIsCreating(true)}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg shadow-blue-500/25 transition-all"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Registrar Venta
                            </button>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    {tenant?.is_pro ? (
                        /* ═══════════════════════════════════════════════════════ */
                        /* KANBAN VIEW (PRO) */
                        /* ═══════════════════════════════════════════════════════ */
                        <DndContext
                            sensors={sensors}
                            collisionDetection={closestCenter}
                            onDragStart={handleDragStart}
                            onDragEnd={handleDragEnd}
                        >
                            <div className="overflow-x-auto pb-4">
                                <div className="flex gap-4 min-w-max">
                                    {COLUMNS.map((column) => (
                                        <KanbanColumn
                                            key={column.id}
                                            id={column.id}
                                            label={column.label}
                                            color={column.color}
                                            orders={orders[column.id] || []}
                                            onAdvance={handleAdvanceOrder}
                                            nextStatus={column.nextStatus}
                                        />
                                    ))}
                                </div>
                            </div>

                            {/* Drag Overlay */}
                            <DragOverlay>
                                {activeOrder && (
                                    <div className="opacity-80">
                                        <KanbanCard order={activeOrder} />
                                    </div>
                                )}
                            </DragOverlay>
                        </DndContext>
                    ) : (
                        /* ═══════════════════════════════════════════════════════ */
                        /* TABLE VIEW (FREE) */
                        /* ═══════════════════════════════════════════════════════ */
                        <>
                            <OrdersTable orders={orders} onAdvance={handleAdvanceOrder} />

                            {/* Upsell Banner */}
                            <div className="mt-6 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl p-4 border border-indigo-200">
                                <div className="flex items-center gap-4">
                                    <div className="p-3 bg-indigo-100 rounded-xl">
                                        <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                        </svg>
                                    </div>
                                    <div className="flex-1">
                                        <h3 className="font-semibold text-gray-900">💡 Tablero Kanban disponible en PRO</h3>
                                        <p className="text-sm text-gray-600">Organiza tu cocina visualmente arrastrando pedidos entre columnas.</p>
                                    </div>
                                    <a
                                        href={`/upgrade/${tenant?.slug}`}
                                        className="px-4 py-2 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 text-sm"
                                    >
                                        Activar PRO
                                    </a>
                                </div>
                            </div>
                        </>
                    )}
                </main>
            </div>
        </TenantLayout>
    );
}
