/**
 * KanbanCard.jsx - Draggable Order Card Component
 *
 * PURPOSE: Pure UI component that renders a single order card.
 * Designed to be draggable via @dnd-kit/core.
 * STATELESS regarding board structure - only receives data.
 *
 * PROPS:
 * - order: { id, order_number, customer_name, total, created_at, items: [] }
 * - onAdvance: (orderId) => void (callback for advancing status)
 * - showAdvanceButton: boolean
 */

import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

// ═══════════════════════════════════════════════════════════════════════════
// CURRENCY FORMATTER (Using Intl API as required)
// ═══════════════════════════════════════════════════════════════════════════
const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 2,
});

// ═══════════════════════════════════════════════════════════════════════════
// TIME FORMATTER
// ═══════════════════════════════════════════════════════════════════════════
function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('es-HN', { hour: '2-digit', minute: '2-digit', hour12: true });
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function KanbanCard({ order, onAdvance, showAdvanceButton = false }) {
    // DnD-Kit Sortable Hook
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: order.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        cursor: isDragging ? 'grabbing' : 'grab',
    };

    // Limit displayed items to first 2
    const displayedItems = order.items?.slice(0, 2) || [];
    const remainingCount = (order.items?.length || 0) - displayedItems.length;

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...listeners}
            className={`bg-white rounded-lg shadow-sm border border-gray-100 p-3 hover:shadow-md transition-shadow
                ${isDragging ? 'ring-2 ring-blue-500 ring-offset-2' : ''}`}
        >
            {/* Header: Order Number & Time */}
            <div className="flex items-start justify-between mb-2">
                <span className="text-xs font-bold text-gray-500">
                    {order.order_number || `#${order.id}`}
                </span>
                <span className="text-xs text-gray-400">
                    {formatTime(order.created_at)}
                </span>
            </div>

            {/* Customer Name */}
            {order.customer_name && (
                <p className="font-medium text-gray-900 text-sm mb-1">
                    {order.customer_name}
                </p>
            )}

            {/* Items Summary */}
            <div className="text-xs text-gray-500 mb-2">
                {displayedItems.map((item, idx) => (
                    <p key={idx}>
                        {item.quantity}x {item.product_name?.length > 20
                            ? item.product_name.slice(0, 20) + '...'
                            : item.product_name}
                    </p>
                ))}
                {remainingCount > 0 && (
                    <p className="text-gray-400">+{remainingCount} más...</p>
                )}
            </div>

            {/* Footer: Total & Action */}
            <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                <span className="font-bold text-gray-900">
                    {currencyFormatter.format(order.total)}
                </span>

                {showAdvanceButton && onAdvance && (
                    <button
                        onClick={(e) => {
                            e.stopPropagation(); // Prevent drag trigger
                            onAdvance(order.id);
                        }}
                        className="px-2 py-1 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors"
                    >
                        Avanzar →
                    </button>
                )}
            </div>
        </div>
    );
}
